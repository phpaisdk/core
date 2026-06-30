<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Exceptions\SchemaValidationException;
use AiSdk\Schema;

/**
 * Single schema validator used by BOTH tool input validation and structured
 * output validation. No duplicated validation logic across the codebase.
 */
final class SchemaValidator
{
    public static function validate(Schema $schema, mixed $value, string $path = 'value'): mixed
    {
        if ($value === null && ($schema->isNullable() || ! $schema->isRequired())) {
            return null;
        }

        if ($value === null) {
            throw self::invalid($path, 'non-null value', $value);
        }

        if (($enum = $schema->enumValues()) !== null && ! in_array($value, $enum, true)) {
            throw self::invalid($path, 'one of: ' . implode(', ', array_map(strval(...), $enum)), $value);
        }

        return match ($schema->type()) {
            Schema::TYPE_STRING => self::assert($value, is_string($value), $path, 'string'),
            Schema::TYPE_INTEGER => self::assert($value, is_int($value), $path, 'integer'),
            Schema::TYPE_NUMBER => self::assert($value, is_int($value) || is_float($value), $path, 'number'),
            Schema::TYPE_BOOLEAN => self::assert($value, is_bool($value), $path, 'boolean'),
            Schema::TYPE_ARRAY => self::validateArray($schema, $value, $path),
            Schema::TYPE_OBJECT => self::validateObject($schema, $value, $path),
            default => $value,
        };
    }

    private static function assert(mixed $value, bool $passes, string $path, string $expected): mixed
    {
        if (! $passes) {
            throw self::invalid($path, $expected, $value);
        }

        return $value;
    }

    /**
     * @return array<int, mixed>
     */
    private static function validateArray(Schema $schema, mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw self::invalid($path, 'array', $value);
        }

        $items = $schema->items();
        if ($items === null) {
            return $value;
        }

        foreach ($value as $index => $item) {
            $value[$index] = self::validate($items, $item, "{$path}.{$index}");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function validateObject(Schema $schema, mixed $value, string $path): array
    {
        if (! is_array($value)) {
            throw self::invalid($path, 'object', $value);
        }

        foreach ($schema->properties() as $name => $property) {
            if (! array_key_exists($name, $value)) {
                if ($property->isRequired()) {
                    throw new SchemaValidationException(
                        message: "Missing required field [{$path}.{$name}].",
                        context: ['path' => "{$path}.{$name}"],
                    );
                }

                continue;
            }

            $value[$name] = self::validate($property, $value[$name], "{$path}.{$name}");
        }

        return $value;
    }

    private static function invalid(string $path, string $expected, mixed $actual): SchemaValidationException
    {
        return new SchemaValidationException(
            message: "[{$path}] must be {$expected}.",
            context: ['path' => $path, 'expected' => $expected, 'actualType' => get_debug_type($actual)],
        );
    }
}
