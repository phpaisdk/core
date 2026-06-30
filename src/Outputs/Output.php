<?php

declare(strict_types=1);

namespace AiSdk\Outputs;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Schema;

/**
 * Describes structured text output.
 */
final class Output
{
    public const string KIND_TEXT = 'text';
    public const string KIND_ENUM = 'enum';
    public const string KIND_OBJECT = 'object';

    private function __construct(
        public readonly string $kind,
        public readonly ?Schema $schema = null,
    ) {}

    /**
     * @param  array<int, string>  $values
     */
    public static function enum(array $values): self
    {
        if ($values === []) {
            throw new InvalidArgumentException('Output::enum() requires at least one value.');
        }

        return new self(self::KIND_ENUM, Schema::enum($values));
    }

    public static function schema(Schema $schema): self
    {
        return $schema->enumValues() === null
            ? new self(self::KIND_OBJECT, $schema)
            : new self(self::KIND_ENUM, $schema);
    }
}
