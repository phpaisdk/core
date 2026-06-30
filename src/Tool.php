<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidToolInputException;
use AiSdk\Exceptions\ToolExecutionException;
use AiSdk\Support\SchemaValidator;

/**
 * A callable tool. Input validation delegates to the single SchemaValidator.
 * Supports inline definition (Tool::make) and class-based tools (__invoke).
 */
class Tool
{
    private string $name = '';
    private string $description = '';

    /** @var array<int, Schema> */
    private array $inputs = [];

    /** @var callable|null */
    private $handler = null;

    /**
     * @param  array<int, Schema>|Schema|null  $input
     */
    public function __construct(
        ?string $name = null,
        string $description = '',
        Schema|array|null $input = null,
        ?callable $handler = null,
    ) {
        $this->name = $name ?? '';
        $this->description = $description;

        match (true) {
            $input instanceof Schema => $this->input($input),
            is_array($input) => $this->inputs($input),
            default => null,
        };

        if ($handler !== null) {
            $this->run($handler);
        }
    }

    public static function make(string|object $tool, string $description = ''): self
    {
        if ($tool instanceof self) {
            return $tool;
        }

        if (is_object($tool)) {
            throw new InvalidArgumentException('Tool::make() accepts a tool instance, class name, or inline tool name.');
        }

        if (class_exists($tool)) {
            $container = null;

            try {
                $container = Generate::sdk()->container;
            } catch (\Throwable) {
                // Direct tool creation should not force HTTP runtime discovery.
            }

            return Support\ToolResolver::resolve($tool, $container)[0];
        }

        return new self($tool, $description);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function __callStatic(string $method, array $arguments): self
    {
        if ($method === 'as') {
            return new self((string) ($arguments[0] ?? ''));
        }

        throw new InvalidArgumentException("Unknown static Tool method [{$method}].");
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): self
    {
        if ($method === 'as') {
            return $this->named((string) ($arguments[0] ?? ''));
        }

        throw new InvalidArgumentException("Unknown Tool method [{$method}].");
    }

    public function named(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function for(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function input(Schema $schema): self
    {
        if ($schema->type() !== Schema::TYPE_OBJECT && ($schema->name() === null || $schema->name() === '')) {
            throw new InvalidArgumentException('Tool::input() primitive schemas must be named.');
        }

        $this->inputs = [$schema];

        return $this;
    }

    /**
     * @param  array<int, Schema>  $schemas
     */
    public function inputs(array $schemas): self
    {
        if ($schemas === []) {
            throw new InvalidArgumentException('Tool::inputs() requires at least one schema.');
        }

        foreach ($schemas as $schema) {
            if (! $schema instanceof Schema) {
                throw new InvalidArgumentException('Tool::inputs() expects Schema instances.');
            }

            if ($schema->name() === null || $schema->name() === '') {
                throw new InvalidArgumentException('Tool::inputs() schemas must be named.');
            }
        }

        $this->inputs = array_values($schemas);

        return $this;
    }

    public function run(callable $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function handler(): ?callable
    {
        return $this->handler ?? (method_exists($this, '__invoke') ? $this : null);
    }

    /**
     * @return array<string, mixed>
     */
    public function inputSchemaForProvider(): array
    {
        if ($this->inputs === []) {
            return ['type' => 'object', 'properties' => new \stdClass()];
        }

        if (count($this->inputs) === 1 && $this->inputs[0]->type() === Schema::TYPE_OBJECT) {
            return $this->inputs[0]->jsonSchema();
        }

        return Schema::object(
            name: $this->name !== '' ? "{$this->name}_input" : 'tool_input',
            properties: $this->inputs,
        )->jsonSchema();
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function call(array $args, ?ToolExecutionContext $context = null): mixed
    {
        $handler = $this->handler();
        if ($handler === null) {
            throw new ToolExecutionException(
                message: "Tool [{$this->name}] has no handler attached.",
                previous: new InvalidArgumentException('missing handler'),
            );
        }

        $arguments = $this->validatedArguments($args);

        if ($context !== null && $this->acceptsContext($handler, count($arguments))) {
            $arguments[] = $context;
        }

        try {
            return $handler(...$arguments);
        } catch (InvalidToolInputException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ToolExecutionException::for($this->name, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    private function validatedArguments(array $args): array
    {
        if ($this->inputs === []) {
            return [];
        }

        if (count($this->inputs) === 1 && $this->inputs[0]->type() === Schema::TYPE_OBJECT) {
            return [SchemaValidator::validate($this->inputs[0], $args, $this->name)];
        }

        $values = [];
        foreach ($this->inputs as $schema) {
            $name = (string) $schema->name();
            if (! array_key_exists($name, $args)) {
                if ($schema->isRequired()) {
                    throw InvalidToolInputException::missing($this->name, $name);
                }

                $values[] = null;

                continue;
            }

            $values[] = SchemaValidator::validate($schema, $args[$name], $name);
        }

        return $values;
    }

    private function acceptsContext(callable $handler, int $argumentCount): bool
    {
        try {
            $reflection = match (true) {
                is_array($handler) => new \ReflectionMethod($handler[0], (string) $handler[1]),
                is_object($handler) && ! $handler instanceof \Closure => new \ReflectionMethod($handler, '__invoke'),
                $handler instanceof \Closure || is_string($handler) => new \ReflectionFunction($handler),
                default => null,
            };
        } catch (\ReflectionException) {
            return false;
        }

        if ($reflection === null) {
            return false;
        }

        $parameters = $reflection->getParameters();
        $parameter = $parameters[$argumentCount] ?? null;
        if ($parameter === null) {
            return false;
        }

        $type = $parameter->getType();

        return $type instanceof \ReflectionNamedType
            && $type->getName() === ToolExecutionContext::class;
    }
}
