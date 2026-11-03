# PHP AI SDK — Core

A framework-agnostic PHP SDK for working with AI providers. Generate text, images, audio, video, embeddings, and structured decisions; build agents with tools, approvals, and conversation storage.

## Installation

```bash
composer require aisdk/core aisdk/openai
```

Requires PHP 8.3 or later. Set `OPENAI_API_KEY` in your environment. Each provider is a separate Composer package; this example installs OpenAI.

## Usage

```php
use AiSdk\Generate;
use AiSdk\OpenAI;

$result = Generate::text('Explain closures in PHP in one sentence.')
    ->model(OpenAI::model('gpt-6.1-sol'))
    ->run();

echo $result->text;
```

Use `->stream()` for streaming responses. The SDK also provides instance-based runtimes for dependency injection and long-running workers.

## Documentation

[Read the documentation](https://phpaisdk.com/docs/0.x/getting-started) for configuration, supported features, and more examples.

[Contributing](https://github.com/phpaisdk/.github/blob/main/CONTRIBUTING.md) · [Support](https://github.com/phpaisdk/.github/blob/main/SUPPORT.md)

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
