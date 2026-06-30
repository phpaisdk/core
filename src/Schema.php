<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Exceptions\InvalidArgumentException;

/**
 * Named JSON Schema node used by tools and structured output. Single fluent
 * class (not class-per-type): one import, ergonomic call site.
 */
final class Schema
{
    public const string TYPE_STRING = 'string';
    public const string TYPE_INTEGER = 'integer';
    public const string TYPE_NUMBER = 'number';
    public const string TYPE_BOOLEAN = 'boolean';
    public const string TYPE_ARRAY = 'array';
    public const string TYPE_OBJECT = 'object';
    public const string TYPE_NULL = 'null';

    /** @var array<string, mixed> */
    private array $definition;

    private bool $required = false;
    private bool $nullable = false;

    /**
     * @param  array<string, mixed>  $definition
     */
    private function __construct(
        private readonly ?string $name,
        ?string $description,
        array $definition,
    ) {
        $this->definition = $definition;

        if ($description !== null) {
            $this->definition['description'] = $description;
        }
    }

    public static function string(?string $name = null, ?string $description = null): self
    {
        return new self($name, $description, ['type' => self::TYPE_STRING]);
    }

    public static function integer(?string $name = null, ?string $description = null): self
    {
        return new self($name, $description, ['type' => self::TYPE_INTEGER]);
    }

    public static function number(?string $name = null, ?string $description = null): self
    {
        return new self($name, $description, ['type' => self::TYPE_NUMBER]);
    }

    public static function boolean(?string $name = null, ?string $description = null): self
    {
        return new self($name, $description, ['type' => self::TYPE_BOOLEAN]);
    }

    public static function array(self $items, ?string $name = null, ?string $description = null): self
    {
        return new self($name, $description, [
            'type' => self::TYPE_ARRAY,
            'items' => $items,
        ]);
    }

    /**
     * @param  array<int, string>  $values
     */
    public static function enum(array $values, ?string $name = null, ?string $description = null): self
    {
        if ($values === []) {
            throw new InvalidArgumentException('Schema::enum() requires at least one value.');
        }

        return new self($name, $description, [
            'type' => self::TYPE_STRING,
            'enum' => array_values($values),
        ]);
    }

    /**
     * @param  array<int, self>  $properties
     */
    public static function object(string $name, ?string $description = null, array $properties = []): self
    {
        foreach ($properties as $property) {
            if (! $property instanceof self) {
                throw new InvalidArgumentException('Schema::object() properties must be Schema instances.');
            }

            if ($property->name() === null || $property->name() === '') {
                throw new InvalidArgumentException('Schema::object() properties must be named schemas.');
            }
        }

        return new self($name, $description, [
            'type' => self::TYPE_OBJECT,
            'properties' => array_values($properties),
        ]);
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        $value = $this->definition['description'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function required(): self
    {
        $clone = clone $this;
        $clone->required = true;

        return $clone;
    }

    public function nullable(): self
    {
        $clone = clone $this;
        $clone->nullable = true;

        return $clone;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function type(): string
    {
        $type = $this->definition['type'] ?? self::TYPE_STRING;

        return is_string($type) ? $type : self::TYPE_STRING;
    }

    public function items(): ?self
    {
        $items = $this->definition['items'] ?? null;

        return $items instanceof self ? $items : null;
    }

    /**
     * @return array<string, self>
     */
    public function properties(): array
    {
        $properties = $this->definition['properties'] ?? [];
        if (! is_array($properties)) {
            return [];
        }

        $named = [];
        foreach ($properties as $property) {
            if ($property instanceof self && $property->name() !== null) {
                $named[$property->name()] = $property;
            }
        }

        return $named;
    }

    /**
     * @return array<int, mixed>|null
     */
    public function enumValues(): ?array
    {
        $enum = $this->definition['enum'] ?? null;

        return is_array($enum) ? array_values($enum) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSchema(): array
    {
        $type = $this->type();
        $schema = ['type' => $this->nullable ? [$type, self::TYPE_NULL] : $type];

        if ($this->name !== null) {
            $schema['title'] = $this->name;
        }

        if (($description = $this->description()) !== null) {
            $schema['description'] = $description;
        }

        if ($type === self::TYPE_OBJECT) {
            $schema['properties'] = [];
            $required = [];

            foreach ($this->properties() as $name => $property) {
                $schema['properties'][$name] = $property->jsonSchema();
                if ($property->isRequired()) {
                    $required[] = $name;
                }
            }

            if ($required !== []) {
                $schema['required'] = $required;
            }

            $schema['additionalProperties'] = false;
        }

        if ($type === self::TYPE_ARRAY) {
            $schema['items'] = $this->items()?->jsonSchema() ?? ['type' => self::TYPE_STRING];
        }

        if (($enum = $this->enumValues()) !== null) {
            $schema['enum'] = $enum;
        }

        return $schema;
    }
}
