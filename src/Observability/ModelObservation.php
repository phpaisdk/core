<?php

declare(strict_types=1);

namespace AiSdk\Observability;

use AiSdk\Contracts\Model;
use AiSdk\Support\Usage;
use Closure;
use Throwable;

/** @internal Shared model-operation observation seam. */
final readonly class ModelObservation
{
    /** @param Closure(ModelEvent): void|null $observer */
    public function __construct(private ?Closure $observer, private ?RunContext $run = null) {}

    public function emit(Model $model, string $phase, string $operation, bool $streaming, int $start, ?Usage $usage = null, ?Throwable $error = null): void
    {
        if ($this->observer === null) {
            return;
        }

        try {
            ($this->observer)(new ModelEvent($phase, $model->provider(), $model->modelId(), $streaming, (hrtime(true) - $start) / 1_000_000, $usage, $error === null ? null : $error::class, $operation, $this->run));
        } catch (Throwable) {
            // Diagnostic failures never invalidate a successful provider call.
        }
    }

    /**
     * @template T
     * @param Closure(): T $call
     * @return T
     */
    public function call(Model $model, string $operation, Closure $call): mixed
    {
        $start = hrtime(true);
        $this->emit($model, 'start', $operation, false, $start);

        try {
            $result = $call();
        } catch (Throwable $error) {
            $this->emit($model, 'error', $operation, false, $start, error: $error);

            throw $error;
        }
        $usage = is_object($result) ? (get_object_vars($result)['usage'] ?? null) : null;
        $this->emit($model, 'finish', $operation, false, $start, $usage instanceof Usage ? $usage : null);

        return $result;
    }
}
