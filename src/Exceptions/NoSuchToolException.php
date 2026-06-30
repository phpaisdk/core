<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

final class NoSuchToolException extends AiSdkException
{
    public static function for(string $tool): self
    {
        return new self(
            message: "Tool [{$tool}] is not available.",
            context: ['tool' => $tool],
        );
    }
}
