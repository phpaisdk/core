# aisdk/core

Framework-agnostic PHP AI SDK core: contracts, fluent API, value objects, streaming, tools, structured output, and PSR integration.

## Installation

```bash
composer require aisdk/core
```

## Basic Usage

```php
use AiSdk\Generate;
use AiSdk\OpenAI;

$result = Generate::text()
    ->model(OpenAI::model('gpt-4o'))
    ->instructions('Write short, clear answers.')
    ->prompt('Explain closures in PHP.')
    ->run();

echo $result->text;
```

Default models allow terse call sites:

```php
Generate::model(OpenAI::model('gpt-4o'));

$result = Generate::text('Explain closures in PHP.')->run();
```

For long-running workers, concurrent applications, or dependency-injected code, use an instance runtime so configuration is not stored in static process state:

```php
use AiSdk\OpenAI\OpenAIOptions;
use AiSdk\OpenAI\OpenAIProvider;
use AiSdk\Support\SdkFactory;

$runtime = (new SdkFactory)
    ->withConnectTimeout(10)
    ->withTimeout(120)
    ->make();

$provider = new OpenAIProvider(OpenAIOptions::fromArray([
    'apiKey' => $_ENV['OPENAI_API_KEY'],
    'sdk' => $runtime,
]));

$sdk = $runtime->withDefaultTextModel($provider->textModel('gpt-4o'));
$result = $sdk->text('Explain closures in PHP.')->run();
```

The static `Generate` and provider entry points remain convenient for scripts and request-scoped applications; the instance API is the safer choice when one PHP process serves multiple independent workloads.

## Structured Output

```php
use AiSdk\Generate;
use AiSdk\OpenAI;
use AiSdk\Schema;

$result = Generate::text()
    ->model(OpenAI::model('gpt-4o'))
    ->prompt('Extract the city and country from: Lahore, Pakistan.')
    ->output(Schema::object(
        name: 'address',
        description: 'The city and country extracted from the prompt.',
        properties: [
            Schema::string(name: 'city')->required(),
            Schema::string(name: 'country')->required(),
        ],
    ))
    ->run();

echo $result->output['city']; // "Lahore"
```

## Tools

```php
use AiSdk\Generate;
use AiSdk\OpenAI;
use AiSdk\Schema;
use AiSdk\Tool;

$weather = Tool::make('weather', 'Get current weather')
    ->input(Schema::string(name: 'city')->required())
    ->run(fn (string $city): string => "Sunny in {$city}");

$result = Generate::text()
    ->model(OpenAI::model('gpt-4o'))
    ->prompt('What is the weather in Lahore?')
    ->tool($weather)
    ->run();
```

Class-based tools:

```php
final class WeatherTool extends Tool
{
    public function __construct()
    {
        $this->as('weather')
            ->for('Get current weather')
            ->input(Schema::string(name: 'city')->required());
    }

    public function __invoke(string $city): string
    {
        return "Sunny in {$city}";
    }
}
```

## Streaming

```php
use AiSdk\Generate;
use AiSdk\OpenAI;

$stream = Generate::text('Tell me a story.')
    ->model(OpenAI::model('gpt-4o'))
    ->stream();

foreach ($stream->chunks() as $chunk) {
    echo $chunk;
}

$result = $stream->run();
```

Stream hooks:

```php
$stream->onChunk(fn (string $text) => log($text))
    ->onFinish(fn (TextResult $result) => log($result->usage))
    ->onError(fn (\Throwable $e) => log($e));
```

## Image Generation

```php
use AiSdk\Generate;
use AiSdk\OpenAI;

$result = Generate::image()
    ->model(OpenAI::image('gpt-image-1'))
    ->prompt('A clean app icon for a PHP AI SDK')
    ->size('1024x1024')
    ->run();

$result->output->save(__DIR__.'/icon.png');
```

Generate multiple images or use portable image options:

```php
$result = Generate::image('Minimal line art of Lahore Fort')
    ->model(OpenAI::image('gpt-image-1'))
    ->count(2)
    ->aspectRatio('1:1')
    ->run();

foreach ($result->images as $index => $image) {
    $image->save(__DIR__."/image-{$index}.png");
}
```

## Speech Generation

```php
use AiSdk\Generate;
use AiSdk\OpenAI;

$result = Generate::speech()
    ->model(OpenAI::speech('gpt-4o-mini-tts'))
    ->input('Welcome to the PHP AI SDK.')
    ->voice('coral')
    ->format('mp3')
    ->run();

$result->output->save(__DIR__.'/welcome.mp3');
```

The portable speech surface supports `input()`, `voice()`, `format()`, and provider-specific options:

```php
$result = Generate::speech('Read this in a warm tone.')
    ->model(OpenAI::speech('gpt-4o-mini-tts'))
    ->providerOptions('openai', [
        'instructions' => 'Speak clearly and warmly.',
    ])
    ->run();
```

## Model IDs and Capabilities

Model IDs are opaque provider values. Packages do not ship model inventories, so new, private, routed, deployed, and locally installed models can be used without waiting for an SDK release:

```php
$result = Generate::text('Hello')
    ->model(OpenAI::model('your-model-id'))
    ->run();
```

Adapter capabilities are internal implementation details. Before sending a request, the SDK verifies that the selected provider adapter can serialize the requested features. The provider remains the authority on whether the exact model accepts those features. Models never need to be registered with the SDK.

Providers whose available models depend on the current account, region, or local runtime may implement `AvailableModelsProviderInterface` and, where supported, `ModelInspectionProviderInterface`. Provider packages should query an authoritative runtime endpoint instead of presenting a bundled list as live availability. For example, Ollama exposes `Ollama::availableModels()` for its local registry and `Ollama::inspectModel()` for informational model details.

## Supported Capabilities

- Text generation (`Generate::text()`)
- Streaming text generation (`->stream()`)
- Image generation (`Generate::image()`)
- Speech generation (`Generate::speech()`)
- Tool calling (`->tool(...)`)
- Structured output (`->output(...)`)
- Provider options passthrough (`->providerOptions(...)`)
- Normalized provider errors

## Testing

```bash
composer test
```

Core ships with fakes and assertions for deterministic testing:

```php
use AiSdk\Testing\Fakes\FakeTextModel;

$fake = FakeTextModel::text('Hello!');

$result = Generate::text('Hi')
    ->model($fake)
    ->run();

expect($result->text)->toBe('Hello!');
```

## Links

- [Project Documentation](https://github.com/phpaisdk)
- [OpenAI Provider](https://github.com/phpaisdk/openai)
- [Anthropic Provider](https://github.com/phpaisdk/anthropic)
