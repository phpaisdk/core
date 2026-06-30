<?php

declare(strict_types=1);

use AiSdk\Exceptions\InvalidToolInputException;
use AiSdk\Generate;
use AiSdk\Schema;
use AiSdk\Support\Sdk;
use AiSdk\Tool;
use AiSdk\ToolExecutionContext;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

afterEach(fn() => Generate::reset());

it('runs an inline tool with a single named input', function () {
    $tool = Tool::make('weather', 'Get weather')
        ->input(Schema::string('city')->required())
        ->run(fn(string $city) => "Sunny in {$city}");

    expect($tool->call(['city' => 'Lahore']))->toBe('Sunny in Lahore');
});

it('runs a tool with multiple inputs', function () {
    $tool = Tool::make('distance')
        ->inputs([
            Schema::string('from')->required(),
            Schema::string('to')->required(),
        ])
        ->run(fn(string $from, string $to) => "{$from}->{$to}");

    expect($tool->call(['from' => 'A', 'to' => 'B']))->toBe('A->B');
});

it('throws on missing required tool input', function () {
    $tool = Tool::make('weather')
        ->input(Schema::string('city')->required())
        ->run(fn(string $city) => $city);

    $tool->call([]);
})->throws(InvalidToolInputException::class);

it('passes execution context when the handler asks for it', function () {
    $tool = Tool::make('weather')
        ->input(Schema::string('city')->required())
        ->run(fn(string $city, ToolExecutionContext $context) => "{$context->toolCallId}:{$context->toolName}:{$city}");

    $context = new ToolExecutionContext(
        toolCallId: 'call_123',
        toolName: 'weather',
        arguments: ['city' => 'Lahore'],
    );

    expect($tool->call(['city' => 'Lahore'], $context))->toBe('call_123:weather:Lahore');
});

it('resolves class-based tools', function () {
    $tool = Tool::make(WeatherTool::class);

    expect($tool->name())->toBe('weather')
        ->and($tool->call(['city' => 'Oslo']))->toBe('Cold in Oslo');
});

it('supports fluent aliases for inline tools without adding parameter shortcuts', function () {
    $tool = Tool::as('weather')
        ->for('Get current weather')
        ->input(Schema::string(name: 'city', description: 'City name')->required())
        ->run(fn(string $city): string => "Sunny in {$city}");

    expect($tool->name())->toBe('weather')
        ->and($tool->description())->toBe('Get current weather')
        ->and($tool->call(['city' => 'Lahore']))->toBe('Sunny in Lahore');
});

it('resolves class-based tools through the configured container', function () {
    $container = new ToolTestContainer([
        InjectedWeatherTool::class => new InjectedWeatherTool(new WeatherService('Hot')),
    ]);

    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    Generate::configure(new Sdk(
        httpClient: new ToolTestHttpClient(),
        requestFactory: $factory,
        streamFactory: $factory,
        container: $container,
    ));

    $request = Generate::text('Weather?')
        ->tool(InjectedWeatherTool::class);

    $property = new ReflectionProperty($request, 'tools');
    $property->setAccessible(true);
    $tools = $property->getValue($request);

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBe($container->get(InjectedWeatherTool::class))
        ->and($tools[0]->call(['city' => 'Lahore']))->toBe('Hot in Lahore');
});

it('uses the configured container when making a class-based tool directly', function () {
    $container = new ToolTestContainer([
        InjectedWeatherTool::class => new InjectedWeatherTool(new WeatherService('Warm')),
    ]);

    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    Generate::configure(new Sdk(
        httpClient: new ToolTestHttpClient(),
        requestFactory: $factory,
        streamFactory: $factory,
        container: $container,
    ));

    $tool = Tool::make(InjectedWeatherTool::class);

    expect($tool)->toBe($container->get(InjectedWeatherTool::class))
        ->and($tool->call(['city' => 'Lahore']))->toBe('Warm in Lahore');
});

final class WeatherTool extends Tool
{
    public function __construct()
    {
        parent::__construct();
        $this->as('weather')
            ->for('Get current weather')
            ->input(Schema::string('city')->required());
    }

    public function __invoke(string $city): string
    {
        return "Cold in {$city}";
    }
}

final class InjectedWeatherTool extends Tool
{
    public function __construct(private WeatherService $weather)
    {
        parent::__construct();
        $this->as('weather')
            ->for('Get current weather')
            ->input(Schema::string('city')->required());
    }

    public function __invoke(string $city): string
    {
        return $this->weather->for($city);
    }
}

final class WeatherService
{
    public function __construct(private readonly string $condition) {}

    public function for(string $city): string
    {
        return "{$this->condition} in {$city}";
    }
}

final class ToolTestContainer implements ContainerInterface
{
    /**
     * @param  array<string, mixed>  $entries
     */
    public function __construct(private readonly array $entries) {}

    public function get(string $id): mixed
    {
        return $this->entries[$id] ?? throw new RuntimeException("Missing container entry [{$id}].");
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

final class ToolTestHttpClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('HTTP should not be called by this test.');
    }
}
