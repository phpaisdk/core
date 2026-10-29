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

When approval is needed, the entire tool batch pauses before any tool executes. Decisions are keyed by tool-call id; `false` sends a denial result to the model. Missing decisions fail closed. `Generate` tool loops reject approval-required tools; use `Agent` for pause/resume. Live sessions emit their `ToolCallEvent` for manual approval and do not automatically execute approval-required tools.

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

For the broader xAI checks, explicitly set `AISDK_LIVE_XAI_ALL=1` and run `tests/XAICapabilityConformanceTest.php` with the same bootstrap. This tests speech codecs and timestamps, file/byte transcription, reasoning, images and vision, five video modes, ephemeral voice credentials, voice text/audio/tool turns, telephony audio, response cancellation and streaming transcription. It uses short clips and closes WebSocket sessions after at most 30 seconds. `ffmpeg` and the sibling `transport` package's installed dependencies are required. Set `AISDK_LIVE_ARTIFACT_DIR` to reuse generated fixture files for focused reruns. Embedding tests are marked incomplete when the account exposes no embedding model. SIP attachment/hangup needs an actual inbound call and remains covered by deterministic provider tests until such a call is available.

## Provider files

File storage is an account-level service, independent of model selection. OpenAI, Anthropic and xAI expose `files()` with the portable `FileStoreInterface`:

```php
use AiSdk\Content;
use AiSdk\Files\FileUpload;
use AiSdk\LocalFile;
use AiSdk\Message;
use AiSdk\OpenAI;

$files = OpenAI::files();
$file = $files->upload(new FileUpload(
    LocalFile::at('/trusted/reports/report.pdf'),
    'report.pdf',
    'application/pdf',
));
$result = Generate::text()->model(OpenAI::model('your-model-id'))
    ->messages([Message::user([
        Content::text('Explain this report.'),
        Content::storedFile($file->reference),
    ])])->run();
$files->delete($file->reference->id);
```

The service also exposes `retrieve($id)`, `list(cursor: $cursor, limit: 20)` returning `FilePage`, and `download($id)` returning a PSR stream. Pass `FilePage::nextCursor` back unchanged. Uploads accept raw string bytes, explicit `LocalFile` values, or seekable PSR streams with known size. Strings are never interpreted as paths or fetched as URLs. Upload payloads use a multipart stream; uploads are not retried implicitly. The request consumes a supplied stream from its beginning. Provider-specific form fields go in the upload's `options` array; keys can use bracket notation for nested form fields.

`Content::storedFile($reference, image: true)` references a stored image. A reference carries its provider name and is rejected when sent to another provider or an adapter that does not support stored files. **This does not enforce end-user ownership:** keep file-id mappings server-side and authorize list, read, attach, download and delete operations in your application. Provider workspaces can be shared by many users. Files are retained until deleted or expired by provider policy; the SDK never silently deletes them.

OpenAI and xAI select Responses for stored-file inputs and external document URLs. OpenAI inline inputs retain the existing Chat route; use `providerOptions('openai', ['api' => 'responses'])` for non-PDF inline documents. Anthropic maps stored documents and images to native file sources. Anthropic only permits downloading files generated by its hosted tools, not your uploaded inputs. xAI uses `pagination_token` and caps pages at 100; its file attachments activate paid server-side attachment search. Upload support does not imply that every model accepts every file format.

For application tests, use `Testing\Fakes\FakeFileStore` with a PSR stream factory. It implements upload, retrieve, page, download and delete without external calls.

## Native decisions and classification

Use `Generate::decision()` when you need a probability, a fixed choice, or a rubric score. This is a separate model capability; it never silently substitutes text generation or invents confidence values.

```php
use AiSdk\Decisions\Question;

$result = Generate::decision(['message' => 'I was charged twice'])
    ->model(OpenAI::model('gpt-6-luna'))
    ->questions(
        Question::predicate('urgent', 'Does this require immediate attention?'),
        Question::choice('department', 'Who should handle this?', [
            'billing' => 'Payments, invoices, and refunds',
            'support' => 'Product usage and technical issues',
        ]),
        Question::score('severity', 'How severe is this issue?', ['Low', 'Moderate', 'High']),
    )->run();

$urgent = $result->answer('urgent')->yes(threshold: 0.9);
$department = $result->answer('department')->value;
$probability = $result->answer('department')->probabilityOf('billing');
$severity = $result->answer('severity')->value; // probability-weighted, may be fractional
```

OpenAI uses `/decisions`; the `aisdk/typesafe` adapter maps the same questions to Jev's `/systemone` endpoint (`predicate` becomes `noul`). Use `TypeSafe::model('jev-latest')` after configuring that provider. Model ids remain opaque; model availability is decided by the provider.

For one boolean: `Generate::decision($text)->model($model)->decide('Is this spam?', threshold: 0.9)`. For collection-style selection, call `choose($question, $items, $describe, threshold: 0.8)`, where `$items` has stable string keys and `$describe` maps each original item to a string description. It returns the original selected item, or null on refusal or a below-threshold choice. This works with PHP objects or enum values without an Illuminate dependency.

OpenAI supports `images(Content::image(...))`, structured JSON evidence, and text/image arrays in question instructions. Stored file ids are not supported by Decisions. The TypeSafe adapter currently supports text/JSON evidence and text instructions; TypeSafe image assets and conversational input mapping remain follow-up work. Question choices currently use nonempty string values. A result contains `answers`, `usage`, and `rawResponse`; each answer has a `kind`, typed `value`, optional `confidence`, and native `probabilities`. Refusal is explicit (`kind === 'refusal'`); calling `yes()` on a refusal throws instead of treating it as false. Thresholds must be chosen using application evaluation data, not interpreted as guarantees.

Missing/duplicate answers, unknown options, invalid probabilities, incomplete distributions, and scores outside the supplied rubric fail locally. `Testing\Fakes\FakeDecisionModel` accepts a `DecisionModelResponse` for deterministic application tests. There are no live API calls in these tests.

## Run events (0.11)

Register an `AiSdk\Observability\Events` listener (`handle(RunEvent $event): void`) or a callable with `onEvent()` on an agent or text request. Agent registration returns a new agent. Text-request registration follows its mutable builder conventions.

```php
$events = new AiSdk\Testing\Fakes\RecordingEvents;
$agent = (new AiSdk\Agent($model, tools: [$tool]))->onEvent($events);
$run = $agent->run('Look up the order');
echo $run->runContext->invocationId;
```

Each run has an immutable `RunContext` snapshot with `invocationId`, optional `parentInvocationId`, `startedAt` and `finishedAt`. `RunStarted`, `StepStarted`, `StepFinished`, `ToolStarted`, `ToolFinished`, `ToolFailed`, `RunFinished`, `RunFailed` and `RunPaused` live in `AiSdk\Observability\Events`. Step indexes start at one; tool durations use monotonic microseconds. Step events include usage, finish reason and tool call ids/names with arguments omitted. Events carry no prompts, generated content, tool arguments, tool outputs or arbitrary exception messages. Failure events report exception classes; `RunFailed::message` is the fixed sanitized text `Run failed.` Listener failures are swallowed independently.

Approval pauses emit `RunPaused`, leave `finishedAt` unset, and preserve the context and step index in the checkpoint. Resume ends the same invocation; it does not emit a second `RunStarted`. Custom stores must persist the optional `AgentCheckpoint::runContext` and `completedSteps` fields to preserve continuity. Older checkpoints without these fields still resume, with a new context. An unconsumed stream does not start a run; abandoning a partially consumed stream does not imply completion.

Tool handlers receive the context through `ToolExecutionContext::runContext`. To explicitly link other application-managed child work, use `Agent::runWithContext($prompt, $parentContext, $scope)` or `PendingTextRequest::withRunContext($parentContext)`. Both create a new invocation linked to that parent, without process-global state.

All generation builders also support `observe(callable(ModelEvent): void)`. This observes provider-operation start, finish and error, includes an `operation` label, and preserves exception propagation. Video emits separate `video.generate` and `video.poll` operations. Direct calls to provider model adapters bypass builder observation. Script failures offline with any `Testing\Fakes\Fake*Model::failWith($sdkException)`.

## Agent middleware (0.11)

`Agent::withMiddleware(Middleware ...$middleware)` returns a new agent. Each middleware implements `handle(StepContext $step, Closure $next): StepOutcome`. First registered is outermost. `$step->request` exposes the outgoing messages, system instructions, tools and generation options; `$step->runContext`, `stepIndex` and `totalUsage` expose execution context. Pass `$step->withRequest($request)` to `$next` to change the immutable request. The downstream request is preserved in `StepOutcome::request`; use the outcome's copy methods when changing its stopping flags.

Call `$next($step)` once, or skip it and return `StepOutcome::fromParts([new TextPart('cached answer')])`. Short-circuits have the same response validation, usage accounting, events and tool approval checks as generated responses. Outcomes require typed response parts, valid unique tool call ids/names and nonnegative token counts, with at most 4096 parts. At most 64 middleware layers may wrap a step. Exceptions propagate and emit `RunFailed`.

```php
use AiSdk\Agents\Middleware\StepBudget;
use AiSdk\Agents\Middleware\Stop;
use AiSdk\StopWhen;

$agent = $agent->withMiddleware(
    new StepBudget(5),
    new Stop(StopWhen::toolCalled('submit_answer')),
);
```

`Stop` evaluates a `StepResult` immediately after generation, before tool execution, with an empty tool-results list. A matching predicate ends the run with the response so far and does not execute its tool calls. `StepBudget` caps generation steps across resumes; it settles tools on the last allowed step (or pauses for approval) before returning `stepLimitReached`. The agent's constructor `maxSteps` remains the hard ceiling.

Middleware may filter or replace outgoing tools. Approval checkpoints persist only the outgoing tool names, never closures; a resumed agent must supply the corresponding handlers in its configured tools. Keep runtime additions in the agent configuration when they need to survive a pause. Custom checkpoint stores should persist `toolNames` along with run identity and completed step count.

Approval applies to every registered `Tool`, including class-based tools and application tools that call a remote service, when `needsApproval()` is set. Provider-hosted tools run inside the provider and are outside the PHP execution loop: they cannot be approved locally. The paused `AgentResult::messages` includes the assistant message and its tool calls, so a UI can announce a call before showing `approvals`.

Unknown calls in a paused batch settle immediately as error tool messages without running other handlers. If an approval tool is removed from the agent or loses its approval flag before resume, its pending call settles as an error and is never executed. Decisions for these unavailable calls may be omitted; stale decisions for their original ids are accepted and ignored. Every still-valid approval requires an explicit boolean decision. Other unexpected ids remain invalid. Registered non-approval siblings keep waiting for the batch decision.

## Sub-agents as tools (0.11)

`Agent::asTool(string $name, string $description, ?Schema $input = null): Tool` delegates to another bounded agent. By default its input is an object with a required string `prompt`. A supplied schema uses the normal tool validator and sends the validated value to the child as JSON. The model receives only the child's answer, never its conversations or resume token.

```php
$research = new AiSdk\Agent($researchModel, instructions: 'Find supporting facts.');
$writer = new AiSdk\Agent($writerModel, tools: [
    $research->asTool('research', 'Research the supplied topic'),
]);
$run = $writer->run('Explain the topic');
```

Children inherit the application scope and parent invocation id. Their run events also reach the parent's listeners, with distinct invocation ids. `AgentResult::totalUsage` includes descendants and completed child steps even when a later child step fails. Usage already counted before a pause is not counted again on resume. Root depth is zero; the default shared `maxDepth` permits three nested levels. Configure it with the agent constructor (0–32); a child can lower its inherited ceiling but cannot raise it. Re-entering an ancestor or exceeding the limit produces an `AgentDepthException` error tool result so the model can recover.

When a child can pause, every parent needs its own checkpoint store, signing key, distinct agent id and nonempty application scope. Parent and child stores may be separate. The parent returns the child's approval calls and a **parent** resume token. Use that parent token to resume: it first resolves the child and then continues the parent. Completed siblings are not replayed; pending siblings retain their original allow/deny decisions, and each agent retains its own generation budget. Repeated child and grandchild pauses work the same way. `AgentResult::pendingChild` exposes the child result and its assistant announcement for nested approval UI; parent `messages` remains the parent's conversation. `ToolPaused` reports a tool waiting for child approval; tool timings measure each active segment, excluding human wait time.

Custom checkpoint storage must preserve the optional `child` continuation and `decisions` fields as well as context, tool names and step counts. Keep checkpoints server-side. Removing a pending sub-agent settles its parent call as an error. Approval tokens remain single-use; do not independently resume a child using its nested token after returning a parent token to the application. Use the same application-owned agent configuration on resume; custom tools that delegate manually must forward `ToolExecutionContext` to retain nesting and scope guarantees.

Use `$agent->withTools($replacement, $additional)` to replace configured tools by name or append new names, and `$agent->without('tool_name')` to remove them. Both return new agents preserving middleware, listeners, nesting identity, signing configuration and budgets. Duplicate names within one override and empty names are rejected. Adding an approval-capable tool still requires checkpoint configuration. Tool objects follow the existing mutable `Tool` API; clones copy the tool list, not application-owned handler objects.

## Streaming agents (0.11)

`Agent::stream(string|array $prompt, string $scope = ''): AgentStream` drives the same private step loop as `run()`, using the model's typed streaming operation. Iteration yields `Streaming\StreamPart` values interleaved with `Observability\RunEvent` values. Middleware short-circuits also emit typed response parts. Model errors throw; they do not silently finish the stream.

```php
$stream = $agent->stream('Research the order', scope: 'user:42');
foreach ($stream as $part) {
    // Handle typed provider parts and run lifecycle events.
}
$run = $stream->result();
if ($run->resumeToken !== null) {
    $continued = $agent->resumeStream($run->resumeToken, $decisions, 'user:42');
    $run = $continued->run();
}
```

`AgentStream::chunks()` yields text deltas, `run()` drains remaining work and returns the final `AgentResult`, `result()` requires completion, and `isComplete()` includes approval pauses as completed stream segments. There is one active consumer; stopping iteration and later draining continues without replaying chunks or tools. Completed iteration is not replayed and no event history is buffered. A stream performs no work until consumed. Discarding a partially consumed stream leaves its work unfinished.

Approval streams use the same scoped, expiring, single-use checkpoints. Resume may use `resume()` or `resumeStream()` regardless of how the pause was produced. Child run events are forwarded through a streaming parent; child model generation currently remains synchronous. Each streamed step is capped at 65,536 provider parts and 16 MiB of text, reasoning and tool argument deltas. The synchronous response's 4096-part validation and existing agent step/nesting budgets also apply.
