<?php

declare(strict_types=1);

namespace AiSdk\Support\Concerns;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Support\ToolResolver;
use AiSdk\Tool;
use AiSdk\ToolChoice;
use Psr\Container\ContainerInterface;

trait HasTools
{
    /** @var array<int, Tool> */
    protected array $tools = [];

    protected ?ToolChoice $toolChoice = null;

    protected ?ContainerInterface $toolContainer = null;

    public function tools(mixed ...$tools): static
    {
        $items = count($tools) === 1 && is_array($tools[0]) ? $tools[0] : $tools;

        $resolved = [];
        foreach (ToolResolver::resolveMany($items, $this->toolContainer) as $tool) {
            $name = $tool->name();
            if ($name === '') {
                throw new InvalidArgumentException('Tools must have a name. Call ->as(...) or pass a name to Tool::make().');
            }
            if (isset($resolved[$name])) {
                throw new InvalidArgumentException("Duplicate tool name [{$name}].");
            }
            $resolved[$name] = $tool;
        }

        $this->tools = array_values($resolved);

        return $this;
    }

    public function tool(mixed $tool): static
    {
        $resolved = ToolResolver::resolveMany([$tool], $this->toolContainer);
        $candidate = $resolved[0] ?? null;
        if (! $candidate instanceof Tool) {
            throw new InvalidArgumentException('Unable to resolve tool.');
        }

        $name = $candidate->name();
        if ($name === '') {
            throw new InvalidArgumentException('Tools must have a name. Call ->as(...) or pass a name to Tool::make().');
        }

        foreach ($this->tools as $existing) {
            if ($existing->name() === $name) {
                throw new InvalidArgumentException("Duplicate tool name [{$name}].");
            }
        }

        $this->tools[] = $candidate;

        return $this;
    }

    public function toolChoice(ToolChoice|string $choice): static
    {
        $this->toolChoice = ToolChoice::from($choice);

        return $this;
    }

}
