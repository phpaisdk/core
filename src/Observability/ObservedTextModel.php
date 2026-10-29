<?php

declare(strict_types=1);

namespace AiSdk\Observability;

use AiSdk\Contracts\TextModelInterface;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Streaming\ErrorPart;
use AiSdk\Streaming\StreamPart;
use AiSdk\Streaming\StreamState;
use AiSdk\Support\Usage;
use Closure;
use Generator;
use Throwable;

/** @internal */
final readonly class ObservedTextModel implements TextModelInterface
{
    /** @param Closure(ModelEvent): void $observer */
    public function __construct(private TextModelInterface $model, private Closure $observer, private ?RunContext $run = null) {}

    public function specificationVersion(): string
    {
        return $this->model->specificationVersion();
    }
    public function provider(): string
    {
        return $this->model->provider();
    }
    public function modelId(): string
    {
        return $this->model->modelId();
    }

    public function generate(TextModelRequest $request): TextModelResponse
    {
        $start = hrtime(true);
        $this->emit('start', false, $start);

        try {
            $response = $this->model->generate($request);
        } catch (Throwable $error) {
            $this->emit('error', false, $start, error: $error);

            throw $error;
        }
        $this->emit('finish', false, $start, $response->usage);

        return $response;
    }

    /** @return Generator<int, StreamPart> */
    public function stream(TextModelRequest $request): Generator
    {
        $start = hrtime(true);
        $this->emit('start', true, $start);
        $state = new StreamState();

        try {
            foreach ($this->model->stream($request) as $part) {
                if ($part instanceof ErrorPart) {
                    throw $part->error;
                }
                $state->record($part);
                yield $part;
            }
            if ($state->partCount() === 0) {
                throw new \AiSdk\Exceptions\InvalidResponseException('The model stream ended without sending any content.');
            }
            $this->emit('finish', true, $start, $state->usage());
        } catch (Throwable $error) {
            $this->emit('error', true, $start, error: $error);

            throw $error;

        }
    }

    private function emit(string $phase, bool $streaming, int $start, ?Usage $usage = null, ?Throwable $error = null): void
    {
        (new ModelObservation($this->observer, $this->run))->emit($this->model, $phase, 'text', $streaming, $start, $usage, $error);
    }
}
