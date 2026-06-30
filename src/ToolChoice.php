<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Exceptions\InvalidArgumentException;

/**
 * Tool selection policy. Providers read the typed fields directly.
 */
final class ToolChoice
{
    public const string AUTO = 'auto';
    public const string NONE = 'none';
    public const string REQUIRED = 'required';
    public const string TOOL = 'tool';

    private function __construct(
        public readonly string $type,
        public readonly ?string $toolName = null,
    ) {}

    public static function auto(): self
    {
        return new self(self::AUTO);
    }

    public static function none(): self
    {
        return new self(self::NONE);
    }

    public static function required(): self
    {
        return new self(self::REQUIRED);
    }

    public static function tool(string $toolName): self
    {
        if ($toolName === '') {
            throw new InvalidArgumentException('ToolChoice::tool() requires a tool name.');
        }

        return new self(self::TOOL, $toolName);
    }

    public static function from(string|self $choice): self
    {
        if ($choice instanceof self) {
            return $choice;
        }

        return match ($choice) {
            self::AUTO => self::auto(),
            self::NONE => self::none(),
            self::REQUIRED => self::required(),
            default => self::tool($choice),
        };
    }
}
