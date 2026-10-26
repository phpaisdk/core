<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Contracts\Model;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Message;
use AiSdk\Outputs\Output;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Results\StepResult;
use AiSdk\Results\TextResult;
use AiSdk\Schema;
use AiSdk\Streaming\Stream;
use AiSdk\Streaming\StreamState;
use AiSdk\Support\Concerns\ConfiguresGeneration;
use AiSdk\Support\Concerns\ControlsToolLoop;
use AiSdk\Support\Concerns\HasMessages;
use AiSdk\Support\Concerns\HasProviderOptions;
use AiSdk\Support\Concerns\HasTools;
use AiSdk\Tool;
use AiSdk\ToolCall;
use AiSdk\ToolResult;
use Psr\Container\ContainerInterface;

/**
 * Fluent text request, composed from focused traits (not a god-base).
 */
final class PendingTextRequest
{
    use ConfiguresGeneration;
    use HasMessages;
    use HasProviderOptions;
    use ControlsToolLoop;
    use HasTools;

    private ?Output $output = null;

    public function __construct(private ?Model $model = null, ?ContainerInterface $container = null)
    {
        $this->toolContainer = $container;
    }

    public function model(Model $model): self
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
        $model = ModelResolver::resolve($this->model, TextModelInterface::class, 'Generate::text()');
        $executor = $this->executor($request->tools);

        $messages = $request->messages;
        $toolCalls = [];
        $toolResults = [];
        $totalUsage = Usage::empty();
        $response = null;

        for ($step = 1; $step <= $request->maxSteps; $step++) {
            $response = $model->generate($request->withMessages($messages));
            $totalUsage = $totalUsage->add($response->usage);
            $calls = $response->toolCalls();
            $results = [];

            if ($calls !== []) {
                array_push($toolCalls, ...$calls);
                $messages[] = Message::assistant($response->text(), toolCalls: $calls);

                foreach ($calls as $call) {
                    $result = $executor->execute($call, $messages);
                    $results[] = $result;
                    $messages[] = $this->toolMessage($result);
                }

                array_push($toolResults, ...$results);
            }

            $stop = $this->stepFinished(new StepResult($step, $response->text(), $calls, $results, $response->finishReason, $response->usage));

            if ($calls === [] || $stop) {
                return $this->resultFromResponse($response, $toolCalls, $toolResults, $totalUsage);
            }
        }

        if (! $response instanceof TextModelResponse) {
            throw new InvalidArgumentException('Generate::text() requires at least one step.');
        }

        return $this->resultFromResponse($response, $toolCalls, $toolResults, $totalUsage);
    }

    public function stream(): Stream
    {
        $request = $this->request();
        $model = ModelResolver::resolve(
            $this->model,
            TextModelInterface::class,
            'Generate::text()->stream()',
        );
        $executor = $this->executor($request->tools);

        $toolCalls = [];
        $toolResults = [];
        $totalUsage = Usage::empty();
        $finalState = null;

        $parts = (function () use ($model, $request, $executor, &$toolCalls, &$toolResults, &$totalUsage, &$finalState): \Generator {
            $messages = $request->messages;

            for ($step = 1; $step <= $request->maxSteps; $step++) {
                $state = new StreamState();

                foreach ($model->stream($request->withMessages($messages)) as $part) {
                    $state->record($part);
                    yield $part;
                }

                $finalState = $state;
                $totalUsage = $totalUsage->add($state->usage());
                $calls = $state->toolCalls();
                $results = [];

                if ($calls !== []) {
                    array_push($toolCalls, ...$calls);
                    $messages[] = Message::assistant($state->text(), toolCalls: $calls);

                    foreach ($calls as $call) {
                        $result = $executor->execute($call, $messages);
                        $results[] = $result;
                        $messages[] = $this->toolMessage($result);
                    }

                    array_push($toolResults, ...$results);
                }

                $stop = $this->stepFinished(new StepResult($step, $state->text(), $calls, $results, $state->finishReason(), $state->usage()));

                if ($calls === [] || $stop) {
                    return;
                }
            }
        })();

        return new Stream($parts, function (StreamState $state) use (&$toolCalls, &$toolResults, &$totalUsage, &$finalState): TextResult {
            $final = $finalState instanceof StreamState ? $finalState : $state;

            return new TextResult(
                text: $final->text(),
                reasoning: $final->reasoning(),
                output: $this->decodeStructured($final->text()),
                toolCalls: $toolCalls === [] ? $final->toolCalls() : $toolCalls,
                toolResults: $toolResults,
                finishReason: $final->finishReason(),
                usage: $final->usage(),
                providerMetadata: $final->providerMetadata(),
                totalUsage: $totalUsage,
            );
        });
    }

    /**
     * @param  array<int, Tool>  $tools
     */
    private function executor(array $tools): ToolExecutor
    {
        return new ToolExecutor($tools, $this->throwOnToolError, $this->exposeToolErrors);
    }

    private function toolMessage(ToolResult $result): Message
    {
        return Message::tool(
            toolCallId: $result->toolCallId,
            output: is_string($result->output) ? $result->output : Json::encode($result->output),
            name: $result->toolName,
        );
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
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
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
    private function resultFromResponse(TextModelResponse $response, array $toolCalls, array $toolResults, Usage $totalUsage): TextResult
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
            totalUsage: $totalUsage,
        );
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
