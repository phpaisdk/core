<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Exceptions\InvalidToolInputException;
use AiSdk\Exceptions\NoSuchToolException;
use AiSdk\Exceptions\SchemaValidationException;
use AiSdk\Message;
use AiSdk\Tool;
use AiSdk\ToolCall;
use AiSdk\ToolExecutionContext;
use AiSdk\ToolResult;
use Throwable;

/**
 * Runs the tool calls of a step for both run() and stream().
 *
 * By default a failing call is not fatal: the model receives an error result it can
 * react to (retry with fixed arguments, pick another tool). Problems with the model's
 * own input are described precisely; failures inside your handler are reported
 * generically so internal details are not sent to the model unless you opt in.
 *
 * @internal
 */
final readonly class ToolExecutor
{
    /**
     * @param  array<int, Tool>  $tools
     */
    public function __construct(
        private array $tools,
        private bool $throwOnError = false,
        private bool $exposeErrors = false,
        private bool $approved = false,
    ) {}

    /**
     * @param  array<int, Message>  $messages
     */
    public function execute(ToolCall $call, array $messages): ToolResult
    {
        $tool = $this->find($call->name);

        if ($tool === null) {
            if ($this->throwOnError) {
                throw NoSuchToolException::for($call->name);
            }

            $available = implode(', ', array_map(static fn(Tool $tool): string => $tool->name(), $this->tools));

            return new ToolResult($call->id, $call->name, ['error' => "Unknown tool [{$call->name}]. Available tools: {$available}."], isError: true);
        }

        if ($tool->requiresApproval() && ! $this->approved) {
            throw new \LogicException('This tool requires approval. Use Agent to pause and resume with an explicit decision.');
        }

        $context = new ToolExecutionContext(
            toolCallId: $call->id,
            toolName: $call->name,
            arguments: $call->arguments,
            messages: $messages,
        );

        try {
            return new ToolResult($call->id, $tool->name(), $tool->call($call->arguments, $context));
        } catch (Throwable $error) {
            if ($this->throwOnError) {
                throw $error;
            }

            return new ToolResult($call->id, $tool->name(), ['error' => $this->describe($call->name, $error)], isError: true);
        }
    }

    private function find(string $name): ?Tool
    {
        foreach ($this->tools as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        return null;
    }

    private function describe(string $name, Throwable $error): string
    {
        if ($error instanceof InvalidToolInputException || $error instanceof SchemaValidationException || $this->exposeErrors) {
            return $error->getMessage();
        }

        return "Tool [{$name}] failed to execute.";
    }
}
