# aisdk/core

<a href="https://github.com/phpaisdk/core/actions"><img alt="GitHub Workflow Status" src="https://img.shields.io/github/actions/workflow/status/phpaisdk/core/tests.yml?branch=main&label=Tests"></a>
<a href="https://packagist.org/packages/aisdk/core"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/aisdk/core"></a>
<a href="https://packagist.org/packages/aisdk/core"><img alt="Latest Version" src="https://img.shields.io/packagist/v/aisdk/core"></a>
<a href="https://packagist.org/packages/aisdk/core"><img alt="License" src="https://img.shields.io/packagist/l/aisdk/core"></a>
<a href="https://whyphp.dev"><img src="https://img.shields.io/badge/Why_PHP-in_2026-7A86E8?style=flat-square&labelColor=18181b" alt="Why PHP in 2026"></a>

------

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

$sdk = $runtime->withDefaultTextModel($provider->model('gpt-4o'));
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
    ->model(OpenAI::model('gpt-image-1'))
    ->prompt('A clean app icon for a PHP AI SDK')
    ->size('1024x1024')
    ->run();

$result->output->save(__DIR__.'/icon.png');
```

Generate multiple images or use portable image options:

```php
$result = Generate::image('Minimal line art of Lahore Fort')
    ->model(OpenAI::model('gpt-image-1'))
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
    ->model(OpenAI::model('gpt-4o-mini-tts'))
    ->input('Welcome to the PHP AI SDK.')
    ->voice('coral')
    ->format('mp3')
    ->run();

$result->output->save(__DIR__.'/welcome.mp3');
```

The portable speech surface supports `input()`, `voice()`, `format()`, and provider-specific options:

```php
$result = Generate::speech('Read this in a warm tone.')
    ->model(OpenAI::model('gpt-4o-mini-tts'))
    ->providerOptions('openai', [
        'instructions' => 'Speak clearly and warmly.',
    ])
    ->run();
```

## Embeddings

Generate one vector or a batch of vectors through the same text-only API:

```php
use AiSdk\Generate;
use AiSdk\OpenAI;

$result = Generate::embedding([
        'First document',
        'Second document',
    ])
    ->model(OpenAI::model('text-embedding-3-small'))
    ->dimensions(512)
    ->run();

$firstVector = $result->output->vector;
$allVectors = $result->embeddings;
```

`dimensions()` is the only portable embedding option. Provider-specific fields such as retrieval task type or truncation can be sent with `providerOptions()` using the provider package's documented field names.

## Transcription

```php
use AiSdk\Content;
use AiSdk\Generate;
use AiSdk\OpenAI;

$result = Generate::transcription(Content::audio(__DIR__.'/meeting.mp3'))
    ->model(OpenAI::model('gpt-4o-transcribe'))
    ->run();

echo $result->output->text;
```

The portable transcription surface accepts typed audio content. Language hints, diarization, timestamp detail, and other provider-specific controls remain available through `providerOptions()`.

## Video Generation

Video providers expose asynchronous jobs through one portable API. `run()` waits for completion; `job()` starts the operation and returns its job descriptor.

```php
$result = Generate::video('A cinematic product reveal')
    ->model($provider->model('video-model-id'))
    ->aspectRatio('16:9')
    ->resolution('1280x720')
    ->duration(8)
    ->run(timeout: 600);

$job = Generate::videoJob('A glass PHP logo rotating in space')
    ->model($provider->model('video-model-id'))
    ->job();
```

## Live Voice, Transcription, and Translation

`AiSdk\Live` is part of core. Provider packages prepare their endpoint,
authentication, messages, and normalized events; the application supplies a
transport that implements core's two small transport contracts.

```php
use AiSdk\Live;
use AiSdk\Live\AudioDelta;
use AiSdk\Live\TranscriptCompleted;
use AiSdk\OpenAI;

$session = Live::voice()
    ->model(OpenAI::model('gpt-realtime-2.1'))
    ->instructions('Answer briefly and clearly.')
    ->voice('marin')
    ->inputAudioFormat('pcm16')
    ->outputAudioFormat('pcm16')
    ->connect($applicationTransport);

$session->sendAudio($pcmBytes);
$session->commitAudio();

foreach ($session->events() as $event) {
    if ($event instanceof AudioDelta) {
        $speaker->write($event->bytes);
    }

    if ($event instanceof TranscriptCompleted) {
        echo $event->text;
    }
}
```

The session API is transport-independent:

```php
$session->sendAudio($bytes);
$session->sendText('Hello');
$session->commitAudio();
$session->clearAudio();
$session->requestResponse();
$session->cancelResponse();
$session->sendToolResult($callId, $result);
$events = $session->events();
$session->close();
```

Use separate builders so an operation only exposes portable fields that apply
to it:

```php
$captions = Live::transcribe()
    ->model($provider->model('live-transcription-model'))
    ->language('en')
    ->audioFormat('pcm16')
    ->connect($applicationTransport);

$translator = Live::translate()
    ->model($provider->model('live-translation-model'))
    ->from('en')
    ->to('es')
    ->inputAudioFormat('pcm16')
    ->outputAudioFormat('pcm16')
    ->connect($applicationTransport);
```

`providerOptions('provider-name', [...])` remains the escape hatch for
provider-only session fields. Proxy, TLS, timeout, buffer, and frame-limit
configuration belongs to the transport instead.

### Ready-made transports

The optional `aisdk/transport` package supplies WebSocket and bidirectional
HTTP/2 implementations:

```bash
composer require aisdk/transport
```

```php
use AiSdk\Transport;

$session = Live::voice()
    ->model(OpenAI::model('gpt-realtime-2.1'))
    ->connect(Transport::auto());
```

`Transport::auto()` chooses from the endpoint prepared by the provider. Use
`Transport::webSocket()` or `Transport::http2()` when the application should
explicitly restrict the network protocol.

### Core without `aisdk/transport`

Core has no Amp or WebSocket dependency. Install any networking library and
implement `AiSdk\Live\Contracts\TransportInterface` plus
`TransportConnectionInterface`. A complete Amp implementation is included in
[`examples/AppWebSocketTransport.php`](examples/AppWebSocketTransport.php):

```bash
composer require amphp/websocket-client:^2.0
```

```php
use App\Ai\AppWebSocketTransport;

$session = Live::voice()
    ->model(OpenAI::model('gpt-realtime-2.1'))
    ->connect(new AppWebSocketTransport());
```

Provider adapters only exchange `TransportFrame::text()` and
`TransportFrame::binary()` with the transport. A custom transport must not
interpret provider event names.

`events()` waits for incoming network data. For continuous, full-duplex audio,
run the microphone sender and event consumer concurrently using the async
runtime chosen by your application. Core itself does not expose Amp, Revolt,
or another event-loop type; the ready-made transport README includes an Amp
example.

### Tools and normalized events

Voice tools use the same core `Tool` objects as text generation. A registered
tool with a handler is executed automatically. The `ToolCallEvent` is still
emitted for observability, followed by an automatic `ToolResultEvent`. Unknown
or handler-less tools remain manual:

```php
foreach ($session->events() as $event) {
    if ($event instanceof \AiSdk\Live\ToolCallEvent && $event->name === 'approval') {
        $session->sendToolResult($event->callId, ['approved' => true]);
    }
}
```

Portable events cover audio and text deltas, append-only transcript deltas,
replaceable transcript updates, completed transcripts, speech boundaries,
interruptions, tool calls and results, usage, response completion, errors, and
closure. Unknown provider messages are preserved as `ProviderEvent` rather
than discarded.

### WebRTC and provider-hosted calls

WebRTC and SIP are connection topologies, not PHP media transports. A browser
uses its native `RTCPeerConnection`; PHP creates a short-lived client secret or
exchanges an SDP offer through the provider adapter:

```php
$secret = Live::voice()
    ->model(OpenAI::model('gpt-realtime-2.1'))
    ->voice('marin')
    ->clientSecret();

$answer = Live::voice()
    ->model(OpenAI::model('gpt-realtime-2.1'))
    ->webRtc($offerSdp);

return ['sdp' => $answer->sdp, 'call_id' => $answer->callId];
```

For an incoming provider-hosted call, first verify the webhook with the
provider package, then control the call through core:

```php
$call = Live::voice()
    ->model(OpenAI::model('gpt-realtime-2.1'))
    ->instructions('You are the support desk.')
    ->call($verifiedWebhook['data']['call_id']);

$call->accept();
$controlSession = $call->connect($applicationTransport);

// Read normalized events and answer tool calls over the control connection.
foreach ($controlSession->events() as $event) {
    // ...
}

$call->hangup();
```

That control connection is sometimes called a sideband connection: the
provider continues carrying WebRTC or SIP media, while the backend attaches a
WebSocket to the same call ID to update the session, observe events, and handle
tools. Providers expose lifecycle methods only where their official protocol
supports them.

## Model IDs and Capabilities

Model IDs are opaque provider values. Packages do not ship model inventories, so new, private, routed, deployed, and locally installed models can be used without waiting for an SDK release:

```php
$result = Generate::text('Hello')
    ->model(OpenAI::model('your-model-id'))
    ->run();
```

`Provider::model()` is the only public provider model selector. The operation resolves that opaque reference to its internal text, image, speech, transcription, embedding, video, or Live adapter when the request executes.

Adapter capabilities are internal implementation details. Before sending a request, the SDK verifies that the selected provider adapter can serialize the requested features. The provider remains the authority on whether the exact model accepts those features. Models never need to be registered with the SDK.

Providers whose available models depend on the current account, region, or local runtime may implement `AvailableModelsProviderInterface` and, where supported, `ModelInspectionProviderInterface`. Provider packages should query an authoritative runtime endpoint instead of presenting a bundled list as live availability. For example, Ollama exposes `Ollama::availableModels()` for its local registry and `Ollama::inspectModel()` for informational model details.

## Supported Capabilities

- Text generation (`Generate::text()`)
- Streaming text generation (`->stream()`)
- Image generation (`Generate::image()`)
- Speech generation (`Generate::speech()`)
- Text embeddings (`Generate::embedding()`)
- Audio transcription (`Generate::transcription()`)
- Video generation and asynchronous jobs (`Generate::video()`, `Generate::videoJob()`)
- Live voice, transcription, and translation (`Live::voice()`, `Live::transcribe()`, `Live::translate()`)
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

Embedding tests can use `AiSdk\Testing\Fakes\FakeEmbeddingModel` in the same way.
Transcription tests can use `AiSdk\Testing\Fakes\FakeTranscriptionModel`.

## Documentation

- [PHP AI SDK documentation](https://phpaisdk.com/docs)
- [Core documentation](https://phpaisdk.com/docs)

## Community

- [Contributing](https://github.com/phpaisdk/.github/blob/main/CONTRIBUTING.md)
- [Support](https://github.com/phpaisdk/.github/blob/main/SUPPORT.md)
- For private security reports, email [security@phpaisdk.com](mailto:security@phpaisdk.com).

## Preparing for 0.10.0

The following APIs are in the upcoming 0.10.0 release. Install matching `^0.10.0` versions of the SDK packages once published.

### Response identity and stream completion

Text results expose `$result->response->id`, `model`, and `rawFinishReason`. Fields are nullable when the provider does not report them. OpenAI-compatible Chat/Responses and Anthropic map them for both synchronous and streamed calls. Other adapters may leave them null.

`Stream::result()` is final-only: it throws before successful completion and rethrows a stored stream or output-validation error. Use `snapshot()` for partial accumulated output, `isComplete()` to check completion, or `run()` to drain the stream. A finalized result is validated once and cached. Replay does not rerun hooks. Each `FinishPart` ends one model step; exhaust the stream to finish a multi-step tool loop. A snapshot is unvalidated and does not imply success; tool calls with incomplete JSON arguments are omitted until decodable. Only one active stream consumer is allowed.

```php
$events = [];
$stream = Generate::text('Hello')->model($model)
    ->observe(function (\AiSdk\Observability\ModelEvent $event) use (&$events): void {
        $events[] = $event;
    })
    ->stream();
$result = $stream->run();
```

Observation emits `start`, then `finish` or `error`, per consumed text-model call. Events contain provider, model, elapsed milliseconds, usage on success, and exception class on failure. They omit prompts, outputs, tool arguments and exception messages. Listener failures are isolated. Abandoned streams do not emit a successful finish. These hooks observe model calls, not tool execution or other modalities.

### Agents and tool approval

`Agent` is a reusable bounded text/tool loop in core. Each run has its own conversation and token accounting. A step limit is shared across approval pauses and resumes.

```php
$send = Tool::make('send_notification')
    ->needsApproval()
    ->run(fn (): string => $notifications->send());

$agent = new \AiSdk\Agent(
    model: $model,
    instructions: 'Help the user with notifications.',
    tools: [$send],
    maxSteps: 5,
    store: $checkpointStore, // implements AiSdk\Agents\CheckpointStore
    signingKey: $applicationSigningKey, // at least 32 random bytes, kept server-side
    id: 'notifications-v1',
);
$run = $agent->run('Send a notification', scope: $authenticatedTenantAndUser);
// Display $run->approvals. Authorize the current user before accepting decisions.
$done = $agent->resume($run->resumeToken, ['provider-call-id' => true], scope: $authenticatedTenantAndUser);
```

When approval is needed, the entire tool batch pauses before any tool executes. Decisions are keyed by tool-call id; `false` sends a denial result to the model. Missing decisions fail closed. `Generate` tool loops reject approval-required tools; use `Agent` for pause/resume.

Implement `CheckpointStore::put()` and **atomic** `consume()` in application-owned storage. Store the complete `AgentCheckpoint` server-side, including its messages and usage. Resume tokens contain a random lookup id and an HMAC bound to scope and agent id; conversation contents never travel in the token. Protect stored conversations as application data. Checkpoints expire after 15 minutes by default. A valid submission consumes the checkpoint before execution, including submissions with invalid decisions. This prevents replay but does not guarantee exactly-once external effects after a process crash; use idempotent tools and application transaction boundaries. Change the agent id when its tool policy changes. Keep tool definitions stable while checkpoints are outstanding.

Agent execution is synchronous in this release. `AgentResult::stepLimitReached` distinguishes a bounded stop from a model's final answer; `result` can be null when a resumed run has no model steps remaining. `messages` includes tool results and can be passed into a later run. Do not treat a new run as a way to reset the budget for an untrusted task automatically.

### Migration and compatibility policy

- Local input files must use `LocalFile::at()` or `LocalFile::within()`; plain strings are never opened as files.
- Replace reads of partial `Stream::result()` with `snapshot()`. Final `result()` calls after full iteration continue to work.
- Tool errors return to the model by default; use `throwOnToolError()` to abort. Handler exception messages remain hidden unless explicitly enabled.
- Use `Provider::model('opaque-id')`; capability-specific public factories are not supported.
- Generation defaults are provider-owned; unset token and sampling options remain null.
- Upgrade SDK packages together for feature-minor releases. The 0.x line can change public contracts between feature minors; patch releases preserve those contracts.
- For 1.0, public factories, builders, DTO fields, stream parts, model interfaces and documented subclass extension points are the compatibility surface. Classes marked `@internal` are excluded. A public API freeze still requires provider conformance and fresh-install validation; passing local unit tests alone does not establish v1 readiness.

### Coordinated release preparation

From the sibling-package workspace, run `python3 core/scripts/release.py 0.10.0` to inspect the dependency-ordered plan. Add `--write` to update SDK Composer constraints. The script excludes Laravel, Symfony and the local aggregate package, includes unchanged publishable packages, rejects prerelease versions and missing/cyclic dependencies, and never commits, pushes, tags or publishes. Release each package at the planned stable version only after its checks and fresh Composer resolution pass. Publish core and shared adapters before dependent providers, waiting for their versions to become available to Composer.

### Opt-in live conformance tests

`tests/LiveProviderConformanceTest.php` checks text, streaming, tool loops and structured output across native, OpenAI and shared compatible adapters against xAI and Concentrate. The suite is skipped unless `AISDK_LIVE_CONFORMANCE=1` is set. It uses `grok-build-0.1` and `ministral-3-3b`, short prompts, 24–96 output-token limits and at most two tool steps per test. Running it consumes credits; model availability and pricing remain provider-owned.

From `core/`, supply `XAI_API_KEY` and `CONCENTRATE_API_KEY` through the environment and run:

```bash
AISDK_LIVE_CONFORMANCE=1 vendor/bin/pest tests/LiveProviderConformanceTest.php --bootstrap scripts/live-bootstrap.php
```

The bootstrap expects the sibling provider source checkouts used by this workspace. To read an existing application's `.env`, set `AISDK_LIVE_AUTOLOAD` to its `vendor/autoload.php` and `AISDK_LIVE_ENV_DIR` to its directory. The application must already provide `vlucas/phpdotenv`; this adds no dependency or path repository to the application. Use a credential store or existing environment configuration, and never pass key values as command arguments. Passing against a compatible endpoint verifies the adapter's live wire behavior; it does not establish coverage of every OpenAI service or model-specific feature.
