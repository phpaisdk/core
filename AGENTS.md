# aisdk/core: guide for coding agents

Framework-agnostic PHP AI SDK core: portable builders, contracts, request/response DTOs, streaming, tools, schemas, testing fakes and the Live (real-time) event model. Every provider package (`aisdk/openai`, `aisdk/anthropic`, ...) builds on it. Requires PHP 8.3+, PSR-18/17/7/11/3 only (no framework).

## Public API in one screen

```php
use AiSdk\Generate;
use AiSdk\Schema;
use AiSdk\Tool;
use AiSdk\Outputs\Output;
use AiSdk\OpenAI; // from aisdk/openai

$result = Generate::text('Summarize this')   // also image(), speech(), transcription(), embedding(), video(), videoJob()
    ->model(OpenAI::model('gpt-5.5'))        // provider model; ids are opaque strings
    ->instructions('Be terse')
    ->tools($tool)->maxSteps(5)              // bounded tool loop
    ->output(Output::schema($schema))        // structured output, validated locally
    ->reasoning('medium')
    ->providerOptions('openai', ['store' => false])
    ->run();                                  // or ->stream()
```

`Live::voice()`, `Live::transcribe()`, `Live::translate()` open real-time sessions over an application-supplied or `aisdk/transport` transport.

## Architecture rules (do not break)

1. **One public model selector: `Provider::model('id')`.** Ids are opaque. Core never validates an id, never ships a catalog, and has no capability-specific public provider factories. `Generate::*()` resolves the capability adapter (a `TextModelInterface`, `ImageModelInterface`, ...) when the operation executes (`Support/ModelResolver`).
2. **Capability-first.** Each modality has: a fluent `Pending*Request` (`Support/`), an immutable request DTO (`Requests/`), a narrow model contract (`Contracts/`), a response DTO (`Responses/`), and a user-facing result (`Results/`). Add new capabilities the same way, and add a fake in `Testing/Fakes/`.
3. **Provider wire details never enter core.** No provider JSON shapes, endpoints, header names or SDK-specific quirks here. Core exposes portable data; providers map it (`Tool::inputSchemaForProvider()` is the only tool serialization core offers).
4. **Do not invent generation defaults.** `TextModelRequest::$maxTokens`, `$temperature` and `$topP` are `null` unless the caller set them. A provider that requires a value applies its own default (Anthropic does).
5. **Typed, normalized errors.** HTTP failures go through `Utils/Errors/HttpErrorNormalizer` into `AiSdk\Exceptions\*` (with provider, status, request id, retry-after). Retries are an opt-in PSR-18 decorator, `Utils/Http/RetryingHttpClient`; do not add hidden retries elsewhere.
6. **Streaming is typed and protocol-neutral.** Models yield `Streaming\StreamPart`s; `Streaming\Stream` and `StreamState` accumulate text, reasoning, tool calls (by slot) and usage. Do not render SSE or any framework response in core.
7. **Tools** are `AiSdk\Tool` with `AiSdk\Schema` inputs, validated centrally by `Support/SchemaValidator`. The tool loop lives in `PendingTextRequest` (`maxSteps`). Subclassing `Tool` (as `aisdk/mcp` does for remote tools) is supported: override `name()`, `description()`, `inputSchemaForProvider()` and `call()`.
8. **Runtime is a PSR bundle.** `Support/Sdk` (immutable) holds the PSR-18 client, PSR-17 factories, optional PSR-3 logger and PSR-11 container; `SdkFactory` builds it with `php-http/discovery` fallback. `Generate`/provider facades keep static state for convenience; do not add more static state, and prefer passing an `Sdk` in tests (`Generate::configure()` / `Generate::reset()`).

## Layout

| Path | Purpose |
|---|---|
| `src/Generate.php`, `src/Live.php` | Static entry points |
| `src/Support/Pending*Request.php` | Fluent builders per capability |
| `src/Requests`, `src/Responses`, `src/Results` | DTOs in, normalized provider output, user results |
| `src/Contracts` | Provider and model interfaces (`BaseProvider`, `BaseModel`) |
| `src/Streaming` | Stream parts, `Stream`, `StreamState` |
| `src/Live` | Real-time events, sessions, transport SPI |
| `src/Outputs`, `src/Schema.php` | Structured output and portable schema |
| `src/Utils/Http` | `HttpRunner` (JSON/SSE over PSR-18), `RetryingHttpClient`, multipart |
| `src/Utils/Errors` | Error normalization |
| `src/Testing/Fakes` | `FakeTextModel` and friends for application tests |
| `tests/` | Pest tests (`tests/Fakes` are test-only doubles) |

## Conventions

- `declare(strict_types=1)`, PSR-4 `AiSdk\` in `src/`, Pint with the `per` preset, PHPStan level 8.
- Value objects are `final` and immutable (`readonly`); `Sdk` and requests return copies via `with*()`.
- Public API changes are releases for ~50 packages: avoid breaking signatures; prefer adding nullable/optional parameters.
- Feature-minor versions (`0.x.0`) release across all packages together; patch releases may ship alone.

## Commands

```bash
composer lint       # pint --test
composer analyze    # phpstan level 8
composer test       # pest
composer test:all
```

Provider packages install a released copy of core in their own `vendor/`. To check a provider against unreleased core changes, run its tests with a bootstrap that prepends `core/src` to the autoloader.

## Docs

- https://phpaisdk.com/llms.txt (index) and https://phpaisdk.com/llms-full.txt (everything)
- Any docs page as Markdown: append `.md`, e.g. https://phpaisdk.com/docs/tools.md
- API reference: https://phpaisdk.com/api-reference/generate.md
