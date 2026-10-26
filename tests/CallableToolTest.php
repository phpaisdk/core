<?php

declare(strict_types=1);

use AiSdk\Attributes\Describe;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Tool;

enum Unit: string
{
    case Celsius = 'celsius';
    case Fahrenheit = 'fahrenheit';
}

#[Describe('Get the weather forecast')]
function forecast_for(
    #[Describe('City name, for example Lahore')]
    string $city,
    int $days = 3,
    Unit $unit = Unit::Celsius,
    bool $verbose = false,
): string {
    return "{$city}|{$days}|{$unit->value}|" . ($verbose ? 'v' : 'q');
}

it('builds the model-facing schema from a function signature', function () {
    $tool = Tool::fromCallable(forecast_for(...), name: 'forecast');
    $schema = $tool->inputSchemaForProvider();

    expect($tool->name())->toBe('forecast')
        ->and($tool->description())->toBe('Get the weather forecast')
        ->and($schema['required'])->toBe(['city'])
        ->and($schema['properties']['city'])->toMatchArray(['type' => 'string', 'description' => 'City name, for example Lahore'])
        ->and($schema['properties']['days']['type'])->toBe('integer')
        ->and($schema['properties']['unit'])->toMatchArray(['type' => 'string', 'enum' => ['celsius', 'fahrenheit']])
        ->and($schema['properties']['verbose']['type'])->toBe('boolean');
});

it('uses the function name and #[Describe] when no name is given', function () {
    $tool = Tool::fromCallable('forecast_for');

    expect($tool->name())->toBe('forecast_for')->and($tool->description())->toBe('Get the weather forecast');
});

it('calls the function with named arguments so defaults still apply', function () {
    $tool = Tool::fromCallable(forecast_for(...), name: 'forecast');

    expect($tool->call(['city' => 'Lahore']))->toBe('Lahore|3|celsius|q')
        ->and($tool->call(['city' => 'Lahore', 'days' => 5, 'unit' => 'fahrenheit', 'verbose' => true]))->toBe('Lahore|5|fahrenheit|v');
});

it('validates model arguments before calling', function () {
    $tool = Tool::fromCallable(forecast_for(...), name: 'forecast');

    expect(fn() => $tool->call([]))->toThrow(Exception::class);
    expect(fn() => $tool->call(['city' => 'Lahore', 'days' => 'many']))->toThrow(Exception::class);
    expect(fn() => $tool->call(['city' => 'Lahore', 'unit' => 'kelvin']))->toThrow(Exception::class);
});

it('supports closures, nullable parameters and invokable objects', function () {
    $tool = Tool::fromCallable(fn(string $q, ?string $lang = null): string => $q . '/' . ($lang ?? 'any'), name: 'search', description: 'Search');
    $schema = $tool->inputSchemaForProvider();

    expect($schema['required'])->toBe(['q'])
        ->and($schema['properties']['lang']['type'])->toBe(['string', 'null'])
        ->and($tool->call(['q' => 'php']))->toBe('php/any');

    $invokable = new class {
        public function __invoke(int $a, int $b): int
        {
            return $a + $b;
        }
    };

    expect(Tool::fromCallable($invokable, name: 'add')->call(['a' => 2, 'b' => 3]))->toBe(5);
});

it('supports tools without parameters', function () {
    $tool = Tool::fromCallable(fn(): string => 'pong', name: 'ping');

    expect($tool->call([]))->toBe('pong');
});

it('refuses closures without a name and unsupported types with a helpful message', function () {
    expect(fn() => Tool::fromCallable(fn(string $a): string => $a))->toThrow(InvalidArgumentException::class, 'needs a name');
    expect(fn() => Tool::fromCallable(fn(string|int $a): string => (string) $a, name: 'x'))->toThrow(InvalidArgumentException::class, 'single type');
    expect(fn() => Tool::fromCallable(fn(DateTimeInterface $d): string => 'x', name: 'x'))->toThrow(InvalidArgumentException::class, 'unsupported type');
});
