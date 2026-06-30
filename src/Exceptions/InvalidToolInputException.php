<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

final class InvalidToolInputException extends AiSdkException
{
    public static function missing(string $tool, string $path): self
    {
        return new self(
            message: "Tool [{$tool}] is missing required input [{$path}].",
            context: ['tool' => $tool, 'path' => $path],
        );
    }

    public static function invalid(string $tool, string $path, string $expected, mixed $actual): self
    {
        return new self(
            message: "Tool [{$tool}] input [{$path}] must be {$expected}.",
            context: [
                'tool' => $tool,
                'path' => $path,
                'expected' => $expected,
                'actualType' => get_debug_type($actual),
            ],
        );
    }
}
