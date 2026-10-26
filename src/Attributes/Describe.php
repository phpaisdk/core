<?php

declare(strict_types=1);

namespace AiSdk\Attributes;

use Attribute;

/**
 * Describes a function (the tool) or one of its parameters for the model.
 *
 *   #[Describe('Get the current weather for a city')]
 *   function weather(#[Describe('City name, e.g. Lahore')] string $city, string $unit = 'celsius'): string
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final readonly class Describe
{
    public function __construct(public string $text) {}
}
