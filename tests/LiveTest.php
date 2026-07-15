<?php

declare(strict_types=1);

it('builds a transcribe session with typed and provider options', function () {
    $model = new class implements \AiSdk\Live\Contracts\LiveModelInterface {
        public ?\AiSdk\Live\LiveRequest $request = null;

        public function specificationVersion(): string
        {
            return 'v1';
        }
        public function provider(): string
        {
            return 'fake';
        }
        public function modelId(): string
        {
            return 'live-test';
        }

        public function createLiveSession(\AiSdk\Live\LiveRequest $request, \AiSdk\Live\Contracts\TransportInterface $transport): \AiSdk\Live\Contracts\LiveSessionDriverInterface
        {
            $this->request = $request;

            return new class implements \AiSdk\Live\Contracts\LiveSessionDriverInterface {
                public function sendAudio(string $bytes): void {}
                public function sendText(string $text): void {}
                public function commitAudio(): void {}
                public function clearAudio(): void {}
                public function requestResponse(): void {}
                public function cancelResponse(): void {}
                public function sendToolResult(string $callId, mixed $result): void {}
                public function events(): iterable
                {
                    return [];
                }
                public function close(): void {}
            };
        }
    };

    $transport = new class implements \AiSdk\Live\Contracts\TransportInterface {
        public function supports(\AiSdk\Live\TransportEndpoint $endpoint): bool
        {
            return true;
        }
        public function connect(\AiSdk\Live\TransportEndpoint $endpoint): \AiSdk\Live\Contracts\TransportConnectionInterface
        {
            throw new LogicException('Not used by the fake model.');
        }
    };

    $session = \AiSdk\Live::transcribe()
        ->model($model)
        ->language('en')
        ->audioFormat('pcm16')
        ->providerOptions('fake', ['punctuation' => true])
        ->connect($transport);

    expect($session)->toBeInstanceOf(\AiSdk\Live\LiveSession::class)
        ->and($model->request?->operation)->toBe(\AiSdk\Live\LiveOperation::Transcribe)
        ->and($model->request?->options)->toBe(['language' => 'en', 'input_audio_format' => 'pcm16'])
        ->and($model->request?->providerOptions)->toBe(['fake' => ['punctuation' => true]]);
});

it('builds voice and translate requests with operation-specific options', function () {
    $model = new class implements \AiSdk\Live\Contracts\LiveModelInterface {
        /** @var list<\AiSdk\Live\LiveRequest> */
        public array $requests = [];

        public function specificationVersion(): string
        {
            return 'v1';
        }

        public function provider(): string
        {
            return 'fake';
        }

        public function modelId(): string
        {
            return 'live-test';
        }

        public function createLiveSession(\AiSdk\Live\LiveRequest $request, \AiSdk\Live\Contracts\TransportInterface $transport): \AiSdk\Live\Contracts\LiveSessionDriverInterface
        {
            $this->requests[] = $request;

            return new class implements \AiSdk\Live\Contracts\LiveSessionDriverInterface {
                public function sendAudio(string $bytes): void {}
                public function sendText(string $text): void {}
                public function commitAudio(): void {}
                public function clearAudio(): void {}
                public function requestResponse(): void {}
                public function cancelResponse(): void {}
                public function sendToolResult(string $callId, mixed $result): void {}
                public function events(): iterable
                {
                    return [];
                }
                public function close(): void {}
            };
        }
    };
    $transport = new class implements \AiSdk\Live\Contracts\TransportInterface {
        public function supports(\AiSdk\Live\TransportEndpoint $endpoint): bool
        {
            return true;
        }

        public function connect(\AiSdk\Live\TransportEndpoint $endpoint): \AiSdk\Live\Contracts\TransportConnectionInterface
        {
            throw new LogicException('Not used by the fake model.');
        }
    };
    $tool = \AiSdk\Tool::make('weather');

    \AiSdk\Live::voice()
        ->model($model)
        ->instructions('Be concise.')
        ->voice('marin')
        ->turnDetection('semantic_vad')
        ->language('en')
        ->inputAudioFormat('pcm16')
        ->outputAudioFormat('pcm16')
        ->tool($tool)
        ->providerOptions('fake', ['session' => ['mode' => 'voice']])
        ->connect($transport);

    \AiSdk\Live::translate()
        ->model($model)
        ->from('en')
        ->to('es')
        ->inputAudioFormat('pcm16')
        ->outputAudioFormat('pcm16')
        ->providerOptions('fake', ['translation' => ['formal' => true]])
        ->connect($transport);

    expect($model->requests)->toHaveCount(2)
        ->and($model->requests[0]->operation)->toBe(\AiSdk\Live\LiveOperation::Voice)
        ->and($model->requests[0]->options)->toBe([
            'instructions' => 'Be concise.',
            'voice' => 'marin',
            'turn_detection' => 'semantic_vad',
            'language' => 'en',
            'input_audio_format' => 'pcm16',
            'output_audio_format' => 'pcm16',
        ])
        ->and($model->requests[0]->tools)->toBe([$tool])
        ->and($model->requests[0]->providerOptions)->toBe(['fake' => ['session' => ['mode' => 'voice']]])
        ->and($model->requests[1]->operation)->toBe(\AiSdk\Live\LiveOperation::Translate)
        ->and($model->requests[1]->options)->toBe([
            'from' => 'en',
            'to' => 'es',
            'input_audio_format' => 'pcm16',
            'output_audio_format' => 'pcm16',
        ])
        ->and($model->requests[1]->tools)->toBe([])
        ->and($model->requests[1]->providerOptions)->toBe(['fake' => ['translation' => ['formal' => true]]]);
});

it('requires a target language for translation and rejects blank portable values', function () {
    $model = new class implements \AiSdk\Live\Contracts\LiveModelInterface {
        public function specificationVersion(): string
        {
            return 'v1';
        }
        public function provider(): string
        {
            return 'fake';
        }
        public function modelId(): string
        {
            return 'live-test';
        }
        public function createLiveSession(\AiSdk\Live\LiveRequest $request, \AiSdk\Live\Contracts\TransportInterface $transport): \AiSdk\Live\Contracts\LiveSessionDriverInterface
        {
            throw new LogicException('Validation must happen first.');
        }
    };
    $transport = new class implements \AiSdk\Live\Contracts\TransportInterface {
        public function supports(\AiSdk\Live\TransportEndpoint $endpoint): bool
        {
            return true;
        }
        public function connect(\AiSdk\Live\TransportEndpoint $endpoint): \AiSdk\Live\Contracts\TransportConnectionInterface
        {
            throw new LogicException('Validation must happen first.');
        }
    };

    expect(fn() => \AiSdk\Live::translate()->model($model)->connect($transport))
        ->toThrow(\AiSdk\Exceptions\InvalidArgumentException::class, 'requires to()')
        ->and(fn() => \AiSdk\Live::voice()->voice('   '))
        ->toThrow(\AiSdk\Exceptions\InvalidArgumentException::class, 'cannot be empty');
});

it('executes registered live tools and sends their result to the provider', function () {
    $driver = new class implements \AiSdk\Live\Contracts\LiveSessionDriverInterface {
        public mixed $result = null;
        public function sendAudio(string $bytes): void {}
        public function sendText(string $text): void {}
        public function commitAudio(): void {}
        public function clearAudio(): void {}
        public function requestResponse(): void {}
        public function cancelResponse(): void {}
        public function sendToolResult(string $callId, mixed $result): void
        {
            $this->result = $result;
        }
        public function events(): iterable
        {
            yield new \AiSdk\Live\ToolCallEvent('call-1', 'weather', []);
        }
        public function close(): void {}
    };

    $session = new \AiSdk\Live\LiveSession($driver, [
        \AiSdk\Tool::make('weather')->run(fn(): string => 'sunny'),
    ]);

    $events = iterator_to_array($session->events());

    $toolResult = $events[1] ?? null;
    if (! $toolResult instanceof \AiSdk\Live\ToolResultEvent) {
        throw new \LogicException('Expected an automatic tool result event.');
    }

    expect($events)->toHaveCount(2)
        ->and($events[0])->toBeInstanceOf(\AiSdk\Live\ToolCallEvent::class)
        ->and($toolResult)->toBeInstanceOf(\AiSdk\Live\ToolResultEvent::class)
        ->and($toolResult->automatic)->toBeTrue()
        ->and($driver->result)->toBe('sunny');
});

it('leaves unknown live tools for manual handling', function () {
    $driver = new class implements \AiSdk\Live\Contracts\LiveSessionDriverInterface {
        public bool $sent = false;
        public function sendAudio(string $bytes): void {}
        public function sendText(string $text): void {}
        public function commitAudio(): void {}
        public function clearAudio(): void {}
        public function requestResponse(): void {}
        public function cancelResponse(): void {}
        public function sendToolResult(string $callId, mixed $result): void
        {
            $this->sent = true;
        }
        public function events(): iterable
        {
            yield new \AiSdk\Live\ToolCallEvent('call-1', 'manual', ['value' => 1]);
        }
        public function close(): void {}
    };

    $events = iterator_to_array((new \AiSdk\Live\LiveSession($driver))->events());

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(\AiSdk\Live\ToolCallEvent::class)
        ->and($driver->sent)->toBeFalse();
});

it('forwards every session action and preserves provider events unchanged', function () {
    $providerEvent = new \AiSdk\Live\ProviderEvent('fake.future', ['value' => true]);
    $driver = new class ($providerEvent) implements \AiSdk\Live\Contracts\LiveSessionDriverInterface {
        /** @var list<array{string, mixed}> */
        public array $actions = [];

        public function __construct(private readonly \AiSdk\Live\ProviderEvent $providerEvent) {}

        public function sendAudio(string $bytes): void
        {
            $this->actions[] = ['audio', $bytes];
        }

        public function sendText(string $text): void
        {
            $this->actions[] = ['text', $text];
        }

        public function commitAudio(): void
        {
            $this->actions[] = ['commit', null];
        }

        public function clearAudio(): void
        {
            $this->actions[] = ['clear', null];
        }

        public function requestResponse(): void
        {
            $this->actions[] = ['request', null];
        }

        public function cancelResponse(): void
        {
            $this->actions[] = ['cancel', null];
        }

        public function sendToolResult(string $callId, mixed $result): void
        {
            $this->actions[] = ['tool', [$callId, $result]];
        }

        public function events(): iterable
        {
            yield $this->providerEvent;
        }

        public function close(): void
        {
            $this->actions[] = ['close', null];
        }
    };
    $session = new \AiSdk\Live\LiveSession($driver);

    $session->sendAudio("\x01\x02");
    $session->sendText('hello');
    $session->commitAudio();
    $session->clearAudio();
    $session->requestResponse();
    $session->cancelResponse();
    $session->sendToolResult('call-1', ['ok' => true]);
    $events = iterator_to_array($session->events());
    $session->close();

    expect($driver->actions)->toBe([
        ['audio', "\x01\x02"],
        ['text', 'hello'],
        ['commit', null],
        ['clear', null],
        ['request', null],
        ['cancel', null],
        ['tool', ['call-1', ['ok' => true]]],
        ['close', null],
    ])->and($events)->toBe([$providerEvent]);
});

it('validates live voice tool collections before provider serialization', function () {
    $tool = \AiSdk\Tool::make('weather');

    expect(fn() => \AiSdk\Live::voice()->tools([$tool, $tool]))
        ->toThrow(\AiSdk\Exceptions\InvalidArgumentException::class, 'Duplicate tool name [weather].')
        ->and(fn() => \AiSdk\Live::voice()->tool(new \AiSdk\Tool()))
        ->toThrow(\AiSdk\Exceptions\InvalidArgumentException::class, 'Tools must have a name.');
});

it('does not suggest generation defaults when a Live model is missing', function () {
    $transport = new class implements \AiSdk\Live\Contracts\TransportInterface {
        public function supports(\AiSdk\Live\TransportEndpoint $endpoint): bool
        {
            return true;
        }

        public function connect(\AiSdk\Live\TransportEndpoint $endpoint): \AiSdk\Live\Contracts\TransportConnectionInterface
        {
            throw new LogicException('Model resolution must happen first.');
        }
    };

    try {
        \AiSdk\Live::voice()->connect($transport);

        throw new \LogicException('Expected Live model resolution to fail.');
    } catch (\AiSdk\Exceptions\InvalidArgumentException $exception) {
        expect($exception->getMessage())
            ->toContain('Live::voice() requires a model')
            ->toContain('Call ->model(...).');
        expect(str_contains($exception->getMessage(), 'Generate::model'))->toBeFalse();
    }
});

it('resolves Live provider models and works with an application transport', function () {
    $connection = new class implements \AiSdk\Live\Contracts\TransportConnectionInterface {
        /** @var list<\AiSdk\Live\TransportFrame> */
        public array $sent = [];

        /** @var list<\AiSdk\Live\TransportFrame> */
        public array $incoming;

        public bool $closed = false;

        public function __construct()
        {
            $this->incoming = [\AiSdk\Live\TransportFrame::text('{"type":"future.event"}')];
        }

        public function send(\AiSdk\Live\TransportFrame $frame): void
        {
            $this->sent[] = $frame;
        }

        public function receive(): ?\AiSdk\Live\TransportFrame
        {
            return array_shift($this->incoming);
        }

        public function finishSending(): void {}

        public function close(): void
        {
            $this->closed = true;
        }

        public function isClosed(): bool
        {
            return $this->closed;
        }
    };
    $transport = new class ($connection) implements \AiSdk\Live\Contracts\TransportInterface {
        public ?\AiSdk\Live\TransportEndpoint $endpoint = null;

        public function __construct(private readonly \AiSdk\Live\Contracts\TransportConnectionInterface $connection) {}

        public function supports(\AiSdk\Live\TransportEndpoint $endpoint): bool
        {
            return $endpoint instanceof \AiSdk\Live\WebSocketEndpoint;
        }

        public function connect(\AiSdk\Live\TransportEndpoint $endpoint): \AiSdk\Live\Contracts\TransportConnectionInterface
        {
            $this->endpoint = $endpoint;

            return $this->connection;
        }
    };
    $adapter = new class implements \AiSdk\Live\Contracts\LiveModelInterface {
        public function specificationVersion(): string
        {
            return 'v1';
        }

        public function provider(): string
        {
            return 'fake';
        }

        public function modelId(): string
        {
            return 'resolved-live-model';
        }

        public function createLiveSession(\AiSdk\Live\LiveRequest $request, \AiSdk\Live\Contracts\TransportInterface $transport): \AiSdk\Live\Contracts\LiveSessionDriverInterface
        {
            $endpoint = new \AiSdk\Live\WebSocketEndpoint(
                'wss://example.test/live',
                ['Authorization' => 'Bearer test'],
                ['fake-live'],
            );
            if (! $transport->supports($endpoint)) {
                throw \AiSdk\Exceptions\UnsupportedTransportException::for($endpoint);
            }

            $connection = $transport->connect($endpoint);

            return new class ($connection) implements \AiSdk\Live\Contracts\LiveSessionDriverInterface {
                public function __construct(private readonly \AiSdk\Live\Contracts\TransportConnectionInterface $connection) {}

                public function sendAudio(string $bytes): void
                {
                    $this->connection->send(\AiSdk\Live\TransportFrame::binary($bytes));
                }

                public function sendText(string $text): void
                {
                    $this->connection->send(\AiSdk\Live\TransportFrame::text($text));
                }

                public function commitAudio(): void {}
                public function clearAudio(): void {}
                public function requestResponse(): void {}
                public function cancelResponse(): void {}
                public function sendToolResult(string $callId, mixed $result): void {}
                public function events(): iterable
                {
                    while (($frame = $this->connection->receive()) !== null) {
                        yield new \AiSdk\Live\ProviderEvent('fake.frame', [
                            'type' => $frame->type->value,
                            'payload' => $frame->payload,
                        ]);
                    }
                }
                public function close(): void
                {
                    $this->connection->close();
                }
            };
        }
    };
    $provider = new class ($adapter) extends \AiSdk\Contracts\BaseProvider implements \AiSdk\Contracts\LiveProviderInterface {
        public ?string $resolvedId = null;

        public function __construct(private readonly \AiSdk\Live\Contracts\LiveModelInterface $adapter) {}

        public function name(): string
        {
            return 'fake';
        }

        protected function liveModel(string $modelId): \AiSdk\Live\Contracts\LiveModelInterface
        {
            $this->resolvedId = $modelId;

            return $this->adapter;
        }
    };

    $session = \AiSdk\Live::voice()
        ->model($provider->model('voice-model'))
        ->connect($transport);
    $session->sendAudio("\x01\x02");
    $events = iterator_to_array($session->events());
    $session->close();

    expect($provider->resolvedId)->toBe('voice-model')
        ->and($transport->endpoint)->toMatchObject([
            'url' => 'wss://example.test/live',
            'headers' => ['Authorization' => 'Bearer test'],
            'subprotocols' => ['fake-live'],
        ])
        ->and($connection->sent)->toHaveCount(1)
        ->and($connection->sent[0]->type)->toBe(\AiSdk\Live\TransportFrameType::Binary)
        ->and($connection->sent[0]->payload)->toBe("\x01\x02")
        ->and($events[0])->toMatchObject([
            'providerType' => 'fake.frame',
            'payload' => ['type' => 'text', 'payload' => '{"type":"future.event"}'],
        ])
        ->and($connection->closed)->toBeTrue();
});

it('distinguishes input and output transcripts', function () {
    $input = new \AiSdk\Live\TranscriptCompleted(
        'Hello',
        'item-input',
        \AiSdk\Live\TranscriptSource::Input,
    );
    $output = new \AiSdk\Live\TranscriptDelta(
        'Hi',
        'item-output',
        \AiSdk\Live\TranscriptSource::Output,
    );

    expect($input->source)->toBe(\AiSdk\Live\TranscriptSource::Input)
        ->and($input->itemId)->toBe('item-input')
        ->and($output->source)->toBe(\AiSdk\Live\TranscriptSource::Output)
        ->and($output->itemId)->toBe('item-output');
});

it('supports provider connection lifecycles independently', function () {
    $model = new class implements \AiSdk\Live\Contracts\LiveClientSecretModelInterface {
        public function specificationVersion(): string
        {
            return 'v1';
        }

        public function provider(): string
        {
            return 'fake';
        }

        public function modelId(): string
        {
            return 'voice-test';
        }

        public function createLiveSession(\AiSdk\Live\LiveRequest $request, \AiSdk\Live\Contracts\TransportInterface $transport): \AiSdk\Live\Contracts\LiveSessionDriverInterface
        {
            throw new LogicException('Not used.');
        }

        public function clientSecret(\AiSdk\Live\LiveRequest $request): \AiSdk\Live\ClientSecret
        {
            return new \AiSdk\Live\ClientSecret('secret', sessionId: $request->operation->value);
        }
    };

    $request = \AiSdk\Live::voice()
        ->model($model)
        ->instructions('Be concise.')
        ->inputAudioFormat('pcm16')
        ->outputAudioFormat('pcm16');

    expect($request->clientSecret()->value)->toBe('secret')
        ->and($request->clientSecret()->sessionId)->toBe('voice')
        ->and(fn() => $request->webRtc('offer'))
        ->toThrow(\AiSdk\Exceptions\InvalidArgumentException::class, 'does not support WebRTC');
});

it('delegates WebRTC and hosted-call lifecycle actions through core values', function () {
    $transport = new class implements \AiSdk\Live\Contracts\TransportInterface {
        public function supports(\AiSdk\Live\TransportEndpoint $endpoint): bool
        {
            return true;
        }

        public function connect(\AiSdk\Live\TransportEndpoint $endpoint): \AiSdk\Live\Contracts\TransportConnectionInterface
        {
            throw new LogicException('The fake call owns its driver.');
        }
    };

    $call = new class implements \AiSdk\Live\Contracts\ProviderLiveCallInterface {
        public bool $accepted = false;
        public bool $hungUp = false;

        public function id(): string
        {
            return 'call-123';
        }

        public function accept(): void
        {
            $this->accepted = true;
        }

        public function connect(\AiSdk\Live\Contracts\TransportInterface $transport): \AiSdk\Live\Contracts\LiveSessionDriverInterface
        {
            return new class implements \AiSdk\Live\Contracts\LiveSessionDriverInterface {
                public function sendAudio(string $bytes): void {}
                public function sendText(string $text): void {}
                public function commitAudio(): void {}
                public function clearAudio(): void {}
                public function requestResponse(): void {}
                public function cancelResponse(): void {}
                public function sendToolResult(string $callId, mixed $result): void {}
                public function events(): iterable
                {
                    yield new \AiSdk\Live\ProviderEvent('fake.connected', ['connected' => true]);
                }
                public function close(): void {}
            };
        }

        public function hangup(): void
        {
            $this->hungUp = true;
        }
    };

    $model = new class ($call) implements \AiSdk\Live\Contracts\LiveConnectionModelInterface {
        public function __construct(private readonly \AiSdk\Live\Contracts\ProviderLiveCallInterface $call) {}

        public function specificationVersion(): string
        {
            return 'v1';
        }

        public function provider(): string
        {
            return 'fake';
        }

        public function modelId(): string
        {
            return 'voice-test';
        }

        public function createLiveSession(\AiSdk\Live\LiveRequest $request, \AiSdk\Live\Contracts\TransportInterface $transport): \AiSdk\Live\Contracts\LiveSessionDriverInterface
        {
            throw new LogicException('Not used.');
        }

        public function clientSecret(\AiSdk\Live\LiveRequest $request): \AiSdk\Live\ClientSecret
        {
            return new \AiSdk\Live\ClientSecret('secret');
        }

        public function webRtc(\AiSdk\Live\LiveRequest $request, string $offerSdp): \AiSdk\Live\WebRtcAnswer
        {
            return new \AiSdk\Live\WebRtcAnswer('answer-' . $offerSdp, 'call-123');
        }

        public function call(\AiSdk\Live\LiveRequest $request, string $callId): \AiSdk\Live\Contracts\ProviderLiveCallInterface
        {
            expect($request->operation)->toBe(\AiSdk\Live\LiveOperation::Voice)
                ->and($callId)->toBe('call-123');

            return $this->call;
        }
    };

    $request = \AiSdk\Live::voice()->model($model);
    $answer = $request->webRtc('offer');
    $liveCall = $request->call('call-123')->accept();
    $events = iterator_to_array($liveCall->connect($transport)->events());
    $liveCall->hangup();

    expect($answer->sdp)->toBe('answer-offer')
        ->and($answer->callId)->toBe('call-123')
        ->and($liveCall->id())->toBe('call-123')
        ->and($call->accepted)->toBeTrue()
        ->and($call->hungUp)->toBeTrue()
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(\AiSdk\Live\ProviderEvent::class);
});
