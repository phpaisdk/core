<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\Generate;
use AiSdk\Live;
use AiSdk\Live\AudioDelta;
use AiSdk\Live\Contracts\TransportConnectionInterface;
use AiSdk\Live\Contracts\TransportInterface;
use AiSdk\Live\LiveClosed;
use AiSdk\Live\LiveError;
use AiSdk\Live\ProviderEvent;
use AiSdk\Live\ResponseCompleted;
use AiSdk\Live\TranscriptCompleted;
use AiSdk\Live\TranscriptUpdate;
use AiSdk\Live\TransportEndpoint;
use AiSdk\LocalFile;
use AiSdk\Support\Sdk;
use AiSdk\Tool;
use AiSdk\XAI;

beforeEach(function () {
    if (getenv('AISDK_LIVE_XAI_ALL') !== '1') {
        $this->markTestSkipped('Set AISDK_LIVE_XAI_ALL=1 to explicitly run billable xAI media and voice tests.');
    }
    if (($_ENV['XAI_API_KEY'] ?? '') === '') {
        throw new RuntimeException('XAI_API_KEY is required.');
    }
    $factory = new GuzzleHttp\Psr7\HttpFactory();
    Generate::configure(new Sdk(new GuzzleHttp\Client(['timeout' => 120, 'connect_timeout' => 10]), $factory, $factory));
    XAI::create(['apiKey' => $_ENV['XAI_API_KEY']]);
});

afterEach(fn() => Generate::reset());

function xaiArtifacts(): string
{
    static $path = null;
    if ($path === null) {
        $configured = getenv('AISDK_LIVE_ARTIFACT_DIR');
        $path = is_string($configured) && $configured !== '' ? $configured : sys_get_temp_dir() . '/aisdk-xai-' . bin2hex(random_bytes(8));
        if (! is_dir($path) && ! mkdir($path, 0700, true)) {
            throw new RuntimeException('Cannot create live artifact directory.');
        }
    }

    return $path;
}

function xaiSpeechFixture(): string
{
    $path = xaiArtifacts() . '/speech.wav';
    if (! is_file($path)) {
        Generate::speech('The number is seven.')->model(XAI::model('grok-voice-latest'))->voice('eve')->format('wav')->run()->output->save($path);
    }

    return $path;
}

function xaiFfmpeg(array $arguments): void
{
    $process = proc_open(['ffmpeg', '-v', 'error', '-y', ...$arguments], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('ffmpeg is required for live audio conversion.');
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('ffmpeg conversion failed: ' . $error);
    }
}

function xaiPcm(int $rate = 16000): string
{
    $path = xaiArtifacts() . '/speech-' . $rate . '.pcm';
    if (! is_file($path)) {
        xaiFfmpeg(['-i', xaiSpeechFixture(), '-f', 's16le', '-acodec', 'pcm_s16le', '-ar', (string) $rate, '-ac', '1', $path]);
    }

    return file_get_contents($path);
}

function xaiImageFixture(): string
{
    $path = xaiArtifacts() . '/image.jpg';
    if (! is_file($path)) {
        Generate::image('A blue circle centered on a white background.')->model(XAI::model('grok-imagine-image'))->aspectRatio('1:1')->providerOptions('xai', ['response_format' => 'b64_json'])->run()->output->save($path);
    }

    return $path;
}

function xaiVideoFixture(): array
{
    $path = xaiArtifacts() . '/video-state.json';
    if (! is_file($path)) {
        $result = Generate::video('A blue circle moves on a white background.')->model(XAI::model('grok-imagine-video-1.5-lite'))->duration(1)->resolution('480p')->run(timeout: 180, pollIntervalMs: 3000);
        file_put_contents($path, json_encode(['url' => $result->output->url], JSON_THROW_ON_ERROR));
    }

    return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

final class XaiConformanceTransport implements TransportInterface
{
    private array $connections = [];
    private array $timers = [];
    public function supports(TransportEndpoint $endpoint): bool
    {
        return (new AiSdk\Transport\WebSocketTransport())->supports($endpoint);
    }
    public function connect(TransportEndpoint $endpoint): TransportConnectionInterface
    {
        $connection = (new AiSdk\Transport\WebSocketTransport())->connect($endpoint);
        $this->connections[] = $connection;
        $this->timers[] = Revolt\EventLoop::delay(30, static function () use ($connection): void {
            if (! $connection->isClosed()) {
                $connection->close();
            }
        });

        return $connection;
    }
    public function close(): void
    {
        foreach ($this->timers as $timer) {
            Revolt\EventLoop::cancel($timer);
        }
        foreach ($this->connections as $connection) {
            if (! $connection->isClosed()) {
                $connection->close();
            }
        }
    }
}

function xaiReady(AiSdk\Live\LiveSession $session): void
{
    foreach ($session->events() as $event) {
        if ($event instanceof LiveError) {
            throw new RuntimeException($event->message);
        }
        if ($event instanceof ProviderEvent && $event->providerType === 'session.updated') {
            return;
        }
    }

    throw new RuntimeException('Voice session did not become ready.');
}

it('generates decodable live xAI speech in every supported codec', function (string $codec) {
    $result = Generate::speech('Seven.')->model(XAI::model('grok-voice-latest'))->providerOptions('xai', ['output_format' => ['codec' => $codec, 'sample_rate' => 16000]])->run();
    expect(strlen($result->output->data))->toBeGreaterThan(100)->and($result->output->mimeType)->toStartWith('audio/');
})->with(['mp3', 'wav', 'pcm', 'mulaw', 'alaw']);

it('decodes live xAI speech timestamps', function () {
    $result = Generate::speech('Seven.')->model(XAI::model('grok-voice-latest'))->providerOptions('xai', ['with_timestamps' => true])->run();
    expect(strlen($result->output->data))->toBeGreaterThan(100)
        ->and($result->output->mimeType)->toStartWith('audio/')
        ->and($result->providerMetadata['xai']['audioTimestamps']['graph_chars'])->not->toBeEmpty();
});

it('transcribes live xAI audio from files and bytes', function (bool $file) {
    $source = $file ? LocalFile::at(xaiSpeechFixture()) : file_get_contents(xaiSpeechFixture());
    $result = Generate::transcription(Content::audio($source, 'audio/wav', 'speech.wav'))->model(XAI::model('grok-voice-transcribe-2.0'))->providerOptions('xai', ['language' => 'en'])->run();
    expect(strtolower($result->output->text))->toMatch('/seven|7/')->and($result->output->duration)->toBeGreaterThan(0);
})->with([true, false]);

it('generates live xAI reasoning synchronously and in a stream', function (bool $streaming) {
    $pending = Generate::text('What is 3 times 7? Reply with just the answer.')->model(XAI::model('grok-4.3'))->reasoning('low')->maxTokens(128);
    $result = $streaming ? $pending->stream()->run() : $pending->run();
    expect($result->text)->toContain('21')->and($result->usage->reasoningTokens)->toBeGreaterThan(0);
})->with([false, true]);

it('generates live xAI images as binary data and hosted URLs', function (string $format) {
    $result = Generate::image('A blue circle on white.')->model(XAI::model('grok-imagine-image'))->providerOptions('xai', ['response_format' => $format])->run();
    if ($format === 'url') {
        expect($result->output->url)->toStartWith('https://');
    } else {
        expect(getimagesizefromstring($result->output->bytes()))->not->toBeFalse();
    }
})->with(['b64_json', 'url']);

it('understands a generated image through live xAI', function () {
    $result = Generate::text()->messages([AiSdk\Message::user([Content::text('What shape is shown? Reply with one word.'), Content::image(LocalFile::at(xaiImageFixture()), 'image/jpeg')])])->model(XAI::model('grok-4.3'))->maxTokens(24)->run();
    expect(strtolower($result->text))->toContain('circle');
});

it('tests every live xAI video generation mode with short clips', function (string $mode) {
    if ($mode === 'text') {
        expect(xaiVideoFixture()['url'])->toStartWith('https://');

        return;
    }
    $pending = Generate::video('Keep the blue circle centered on a light grey background.')->model(XAI::model($mode === 'image' ? 'grok-imagine-video-1.5-lite' : 'grok-imagine-video'));
    if ($mode === 'image') {
        $pending->image(Content::image(LocalFile::at(xaiImageFixture()), 'image/jpeg'))->duration(1)->resolution('480p');
    } elseif ($mode === 'reference') {
        $pending->providerOptions('xai', ['referenceImageUrls' => ['data:image/jpeg;base64,' . base64_encode(file_get_contents(xaiImageFixture()))]])->duration(1)->resolution('480p');
    } elseif ($mode === 'edit') {
        $pending->video(Content::video(xaiVideoFixture()['url']));
    } else {
        $source = xaiArtifacts() . '/video-source.mp4';
        $extended = xaiArtifacts() . '/video-source-2s.mp4';
        file_put_contents($source, Generate::sdk()->httpClient->sendRequest(Generate::sdk()->requestFactory->createRequest('GET', xaiVideoFixture()['url']))->getBody()->getContents());
        xaiFfmpeg(['-stream_loop', '1', '-i', $source, '-t', '2', '-c', 'copy', $extended]);
        $pending->video(Content::video('data:video/mp4;base64,' . base64_encode(file_get_contents($extended))))->duration(2)->providerOptions('xai', ['mode' => 'extend-video']);
    }
    $result = $pending->run(timeout: 180, pollIntervalMs: 3000);
    expect($result->output->url)->toStartWith('https://');
    if ($mode === 'extend') {
        expect($result->output->duration)->toBeNull()->and($result->providerMetadata['xai']['extensionDuration'])->toBe(2.0);
    }
})->with(['text', 'image', 'reference', 'edit', 'extend']);

it('issues a live xAI client secret without exposing it', function () {
    $secret = Live::voice()->model(XAI::model('grok-voice-latest'))->providerOptions('xai', ['expires_after' => ['seconds' => 60]])->clientSecret();
    expect(strlen($secret->value))->toBeGreaterThan(10);
});

it('runs live xAI voice text audio tools and telephony', function (string $mode) {
    $transport = new XaiConformanceTransport();
    $executions = 0;

    try {
        $pending = Live::voice()->model(XAI::model('grok-voice-latest'))->instructions('Reply with at most three spoken words.')->turnDetection(null)->providerOptions('xai', ['reasoning' => ['effort' => 'none']]);
        if ($mode === 'tools') {
            $pending->tool(Tool::fromCallable(function (int $a, int $b) use (&$executions): int {
                $executions++;

                return $a + $b;
            }, name: 'sum_numbers'));
        }
        if ($mode === 'telephony') {
            $pending->inputAudioFormat('g711_ulaw')->outputAudioFormat('g711_alaw');
        }
        $session = $pending->connect($transport);
        xaiReady($session);
        if ($mode === 'audio') {
            $session->sendAudio(xaiPcm(24000));
            $session->commitAudio();
        } elseif ($mode === 'telephony') {
            $path = xaiArtifacts() . '/speech.ulaw';
            xaiFfmpeg(['-i', xaiSpeechFixture(), '-f', 'mulaw', '-ar', '8000', '-ac', '1', $path]);
            $session->sendAudio(file_get_contents($path));
            $session->commitAudio();
        } else {
            $session->sendText($mode === 'tools' ? 'Use sum_numbers with a=2 and b=3, then speak the result.' : 'Say seven.');
        }
        $session->requestResponse();
        $bytes = 0;
        $done = false;
        foreach ($session->events() as $event) {
            if ($event instanceof LiveError) {
                throw new RuntimeException($event->message);
            }
            if ($event instanceof AudioDelta) {
                $bytes += strlen($event->bytes);
            }
            if ($event instanceof ResponseCompleted && ($mode !== 'tools' || ($executions > 0 && $bytes > 0))) {
                $done = true;
                break;
            }
        }
        expect($done)->toBeTrue()->and($bytes)->toBeGreaterThan(100);
        if ($mode === 'tools') {
            expect($executions)->toBe(1);
        }
    } finally {
        $transport->close();
    }
})->with(['text', 'audio', 'tools', 'telephony']);

it('clears live audio and cancels an active xAI response', function () {
    $transport = new XaiConformanceTransport();

    try {
        $session = Live::voice()->model(XAI::model('grok-voice-latest'))->turnDetection(null)->providerOptions('xai', ['reasoning' => ['effort' => 'none']])->connect($transport);
        xaiReady($session);
        $session->sendAudio(str_repeat("\0", 4800));
        $session->clearAudio();
        $cleared = false;
        foreach ($session->events() as $event) {
            if ($event instanceof LiveError) {
                throw new RuntimeException($event->message);
            }
            if ($event instanceof ProviderEvent && $event->providerType === 'input_audio_buffer.cleared') {
                $cleared = true;
                break;
            }
        }
        expect($cleared)->toBeTrue();
        $session->sendText('Count slowly from one to fifty.');
        $session->requestResponse();
        $cancelled = false;
        $sent = false;
        foreach ($session->events() as $event) {
            if ($event instanceof LiveError) {
                throw new RuntimeException($event->message);
            }
            if ($event instanceof AudioDelta && ! $sent) {
                $session->cancelResponse();
                $sent = true;
            }
            if ($event instanceof AiSdk\Live\Interrupted) {
                $cancelled = true;
                break;
            }
        }
        expect($cancelled)->toBeTrue();
    } finally {
        $transport->close();
    }
});

it('flushes live xAI transcription and closes cleanly', function () {
    $transport = new XaiConformanceTransport();

    try {
        $session = Live::transcribe()->model(XAI::model('grok-voice-transcribe-2.0'))->language('en')->audioFormat('pcm16')->providerOptions('xai', ['sample_rate' => 16000])->connect($transport);
        foreach (str_split(xaiPcm(), 3200) as $chunk) {
            $session->sendAudio($chunk);
        }
        $session->commitAudio();
        $session->close();
        $text = '';
        $closed = false;
        foreach ($session->events() as $event) {
            if ($event instanceof LiveError) {
                throw new RuntimeException($event->message);
            }
            if (($event instanceof TranscriptCompleted || $event instanceof TranscriptUpdate) && $event->text !== '') {
                $text = $event->text;
            }
            if ($event instanceof LiveClosed) {
                $closed = true;
            }
        }
        expect(strtolower($text))->toMatch('/seven|7/')->and($closed)->toBeTrue();
    } finally {
        $transport->close();
    }
});

it('tests an enabled xAI embedding model from account metadata', function () {
    $runner = AiSdk\Utils\Http\HttpRunner::fromSdk(Generate::sdk());
    $metadata = $runner->getJson('https://api.x.ai/v1/embedding-models', ['Authorization' => 'Bearer ' . $_ENV['XAI_API_KEY']], 'xai');
    $model = $metadata['models'][0] ?? null;
    $id = is_string($model) ? $model : ($model['id'] ?? $model['name'] ?? null);
    if (! is_string($id) || $id === '') {
        $this->markTestIncomplete('This xAI account exposes no embedding model in /embedding-models.');
    }
    $result = Generate::embedding(['seven', 'eight'])->model(XAI::model($id))->run();
    expect($result->embeddings)->toHaveCount(2)->and(count($result->output->vector))->toBeGreaterThan(0);
});
