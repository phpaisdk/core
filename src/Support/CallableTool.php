<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Attributes\Describe;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Schema;
use AiSdk\Tool;
use BackedEnum;
use ReflectionEnum;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Builds a Tool from a typed PHP callable by reflecting its signature.
 *
 * @internal Use Tool::fromCallable().
 */
final class CallableTool
{
    private function __construct() {}

    public static function make(callable $callable, ?string $name, ?string $description): Tool
    {
        $reflection = self::reflect($callable);
        $name ??= self::defaultName($reflection);

        if ($name === '' || str_starts_with($name, '{closure')) {
            throw new InvalidArgumentException('Tool::fromCallable() needs a name for closures. Pass name: "..." or use a named function or method.');
        }

        $properties = [];
        $enums = [];

        foreach ($reflection->getParameters() as $parameter) {
            [$schema, $enum] = self::schemaFor($parameter, $name);
            $properties[] = $schema;

            if ($enum !== null) {
                $enums[$parameter->getName()] = $enum;
            }
        }

        $description ??= self::describe($reflection) ?? '';
        $tool = Tool::make($name, $description);

        if ($properties !== []) {
            $tool->input(Schema::object($name . '_input', properties: $properties));
        }

        return $tool->run(static function (array $input = []) use ($callable, $enums): mixed {
            foreach ($enums as $parameter => $enumClass) {
                if (isset($input[$parameter]) && is_string($input[$parameter])) {
                    $input[$parameter] = $enumClass::from($input[$parameter]);
                }
            }

            return $callable(...$input);
        });
    }

    private static function reflect(callable $callable): ReflectionFunctionAbstract
    {
        return match (true) {
            is_array($callable) => new ReflectionMethod($callable[0], $callable[1]),
            is_string($callable) && str_contains($callable, '::') => new ReflectionMethod($callable),
            is_object($callable) && ! $callable instanceof \Closure => new ReflectionMethod($callable, '__invoke'),
            default => new ReflectionFunction($callable instanceof \Closure || is_string($callable) ? $callable : \Closure::fromCallable($callable)),
        };
    }

    private static function defaultName(ReflectionFunctionAbstract $reflection): string
    {
        $name = $reflection->getName();

        if (str_starts_with($name, '{closure')) {
            return '';
        }

        return $name === '__invoke' && $reflection instanceof ReflectionMethod
            ? self::snake($reflection->getDeclaringClass()->getShortName())
            : self::snake($name);
    }

    private static function describe(ReflectionFunctionAbstract $reflection): ?string
    {
        $attributes = $reflection->getAttributes(Describe::class);

        return $attributes === [] ? null : $attributes[0]->newInstance()->text;
    }

    /**
     * @return array{0: Schema, 1: class-string<BackedEnum>|null}
     */
    private static function schemaFor(ReflectionParameter $parameter, string $tool): array
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            throw new InvalidArgumentException("Parameter \${$parameter->getName()} of tool [{$tool}] needs a single type (int, float, string, bool, array or a backed enum). Union and intersection types cannot be described to a model.");
        }

        $attributes = $parameter->getAttributes(Describe::class);
        $description = $attributes === [] ? null : $attributes[0]->newInstance()->text;
        $name = $parameter->getName();
        $enum = null;

        if (! $type->isBuiltin() && is_a($type->getName(), BackedEnum::class, true)) {
            $backing = (new ReflectionEnum($type->getName()))->getBackingType();

            if (! $backing instanceof ReflectionNamedType || $backing->getName() !== 'string') {
                throw new InvalidArgumentException("Parameter \${$name} of tool [{$tool}] uses an enum that is not string-backed. Only string-backed enums can be described to a model.");
            }

            $enum = $type->getName();
        }

        $schema = match (true) {
            $type->getName() === 'string' => Schema::string($name, $description),
            $type->getName() === 'int' => Schema::integer($name, $description),
            $type->getName() === 'float' => Schema::number($name, $description),
            $type->getName() === 'bool' => Schema::boolean($name, $description),
            $type->getName() === 'array' => Schema::array(Schema::string(), $name, $description),
            $enum !== null => self::enumSchema($enum, $name, $description),
            default => throw new InvalidArgumentException("Parameter \${$name} of tool [{$tool}] has unsupported type [{$type->getName()}]. Use int, float, string, bool, array or a backed enum."),
        };

        if ($type->allowsNull()) {
            $schema = $schema->nullable();
        }

        if (! $parameter->isOptional() && ! $type->allowsNull()) {
            $schema = $schema->required();
        }

        return [$schema, $enum];
    }

    /**
     * @param  class-string<BackedEnum>  $class
     */
    private static function enumSchema(string $class, string $name, ?string $description): Schema
    {
        $cases = (new ReflectionEnum($class))->getCases();
        $values = array_map(static fn($case): string => (string) $case->getBackingValue(), $cases);

        return Schema::enum($values, $name, $description);
    }

    private static function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
