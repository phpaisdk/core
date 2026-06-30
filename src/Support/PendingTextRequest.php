<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Capability;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\NoSuchToolException;
use AiSdk\Message;
use AiSdk\Outputs\Output;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Results\TextResult;
use AiSdk\Schema;
use AiSdk\Streaming\Stream;
use AiSdk\Support\Concerns\ConfiguresGeneration;
use AiSdk\Support\Concerns\HasMessages;
use AiSdk\Support\Concerns\HasProviderOptions;
use AiSdk\Support\Concerns\HasTools;
use AiSdk\Tool;
use AiSdk\ToolCall;
use AiSdk\ToolExecutionContext;
use AiSdk\ToolResult;

/**
 * Fluent text request, composed from focused traits (not a god-base).
 */
final class PendingTextRequest
{
    use ConfiguresGeneration;
    use HasMessages;
    use HasProviderOptions;
    use HasTools;

    private ?Output $output = null;

    public function __construct(private ?TextModelInterface $model = null) {}

    public function model(TextModelInterface $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function output(Output|Schema $output): self
    {
        $this->output = $output instanceof Output ? $output : Output::schema($output);

        return $this;
    }

    public function run(): TextResult
    {
        $request = $this->request();
        $required = $this->requiredCapabilities($request);

        $model = ModelResolver::resolve($this->model, TextModelInterface::class, 'Generate::text()', $required);

        $messages = $request->messages;
        $toolCalls = [];
        $toolResults = [];
        $response = null;

        for ($step = 1; $step <= $request->maxSteps; $step++) {
            $response = $model->generate($request->withMessages($messages));
            $calls = $response->toolCalls();

            if ($calls === []) {
                return $this->resultFromResponse($response, $toolCalls, $toolResults);
            }

            array_push($toolCalls, ...$calls);
            $messages[] = Message::assistant($response->text(), toolCalls: $calls);

            foreach ($calls as $call) {
                $result = $this->executeToolCall($call, $request->tools, $messages);
                $toolResults[] = $result;
                $messages[] = Message::tool(
                    toolCallId: $result->toolCallId,
                    output: is_string($result->output) ? $result->output : Json::encode($result->output),
                    name: $result->toolName,
                );
            }
        }

        if (! $response instanceof TextModelResponse) {
            throw new InvalidArgumentException('Generate::text() requires at least one step.');
        }

        return $this->resultFromResponse($response, $toolCalls, $toolResults);
    }

    public function stream(): Stream
    {
        $request = $this->request();
        $required = $this->requiredCapabilities($request);
        $required[] = Capability::Streaming;

        $model = ModelResolver::resolve(
            $this->model,
            TextModelInterface::class,
            'Generate::text()->stream()',
            $required,
        );

        return new Stream($model->stream($request));
    }

    /**
     * @return array<int, Capability>
     */
    private function requiredCapabilities(TextModelRequest $request): array
    {
        $required = [Capability::TextGeneration];

        if ($this->output !== null) {
            $required[] = Capability::StructuredOutput;
        }
        if ($this->tools !== []) {
            $required[] = Capability::ToolCalling;
        }
        if ($this->reasoning !== null) {
            $required[] = Capability::Reasoning;
        }

        foreach ($request->messages as $message) {
            foreach ($message->content as $content) {
                $required[] = match ($content->type) {
                    \AiSdk\Content::TYPE_TEXT => Capability::TextInput,
                    \AiSdk\Content::TYPE_IMAGE => Capability::ImageInput,
                    \AiSdk\Content::TYPE_AUDIO => Capability::AudioInput,
                    \AiSdk\Content::TYPE_FILE => Capability::FileInput,
                    default => Capability::TextInput,
                };
            }
        }

        return array_values(array_unique($required, SORT_REGULAR));
    }

    private function request(): TextModelRequest
    {
        if ($this->messages === []) {
            throw new InvalidArgumentException('Generate::text() requires a prompt or messages.');
        }

        return new TextModelRequest(
            messages: $this->messages,
            system: $this->instructions,
            output: $this->output,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            maxTokens: $this->maxTokens ?? 1024,
            temperature: $this->temperature ?? 1.0,
            topP: $this->topP,
            maxSteps: $this->maxSteps,
            reasoning: $this->reasoning,
            providerOptions: $this->readProviderOptions(),
        );
    }

    /**
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<int, ToolResult>  $toolResults
     */
    private function resultFromResponse(TextModelResponse $response, array $toolCalls, array $toolResults): TextResult
    {
        return new TextResult(
            text: $response->text(),
            reasoning: $response->reasoning(),
            output: $this->decodeStructured($response->text()),
            toolCalls: $toolCalls,
            toolResults: $toolResults,
            finishReason: $response->finishReason,
            usage: $response->usage,
            rawResponse: $response->rawResponse,
            providerMetadata: $response->providerMetadata,
        );
    }

    /**
     * @param  array<int, Tool>  $tools
     * @param  array<int, Message>  $messages
     */
    private function executeToolCall(ToolCall $call, array $tools, array $messages): ToolResult
    {
        $tool = null;
        foreach ($tools as $candidate) {
            if ($candidate->name() === $call->name) {
                $tool = $candidate;
                break;
            }
        }

        if ($tool === null) {
            throw NoSuchToolException::for($call->name);
        }

        $context = new ToolExecutionContext(
            toolCallId: $call->id,
            toolName: $call->name,
            arguments: $call->arguments,
            messages: $messages,
        );

        return new ToolResult($call->id, $tool->name(), $tool->call($call->arguments, $context));
    }

    private function decodeStructured(string $text): mixed
    {
        if ($this->output === null || $this->output->kind === Output::KIND_TEXT) {
            return null;
        }

        $decoded = Json::decodeValue(trim($text), 'structured output');

        return $this->output->schema === null
            ? $decoded
            : SchemaValidator::validate($this->output->schema, $decoded, 'output');
    }
}
