<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

final class ToolExecutionException extends AiSdkException
{
    public static function for(string $tool, \Throwable $previous): self
    {
        return new self(
            message: "Tool [{$tool}] threw during execution: {$previous->getMessage()}",
            context: ['tool' => $tool],
            previous: $previous,
        );
    }
}
