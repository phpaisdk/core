<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Tool;
use AiSdk\ToolPackageInterface;
use Psr\Container\ContainerInterface;

final class ToolResolver
{
    /**
     * @param  iterable<mixed>  $items
     * @return array<int, Tool>
     */
    public static function resolveMany(iterable $items, ?ContainerInterface $container = null): array
    {
        $tools = [];

        foreach ($items as $item) {
            array_push($tools, ...self::resolve($item, $container));
        }

        return $tools;
    }

    /**
     * @return array<int, Tool>
     */
    public static function resolve(mixed $item, ?ContainerInterface $container = null): array
    {
        return match (true) {
            $item instanceof Tool => [$item],
            $item instanceof ToolPackageInterface => self::resolveMany($item->tools(), $container),
            is_string($item) => [self::resolveString($item, $container)],
            is_iterable($item) => self::resolveMany($item, $container),
            is_object($item) => throw new InvalidArgumentException('Tools must be Tool instances, Tool class names, inline tool names, packages, or iterables.'),
            default => throw new InvalidArgumentException('Tools must be Tool instances, Tool class names, inline tool names, packages, or iterables.'),
        };
    }

    private static function resolveString(string $tool, ?ContainerInterface $container): Tool
    {
        if (! class_exists($tool)) {
            return Tool::make($tool);
        }

        if (! is_subclass_of($tool, Tool::class)) {
            throw new InvalidArgumentException("Tool class [{$tool}] must extend [" . Tool::class . '].');
        }

        if ($container !== null && $container->has($tool)) {
            $resolved = $container->get($tool);
            if (! $resolved instanceof Tool) {
                throw new InvalidArgumentException("Container entry [{$tool}] must resolve to a Tool instance.");
            }

            return $resolved;
        }

        try {
            return new $tool();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Tool class [{$tool}] could not be constructed. Configure a PSR-11 container or pass a Tool instance.", previous: $e);
        }
    }
}
