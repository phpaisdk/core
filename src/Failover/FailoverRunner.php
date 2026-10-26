<?php

declare(strict_types=1);

namespace AiSdk\Failover;

use AiSdk\Contracts\Model;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\NoSuchModelException;
use AiSdk\Support\ModelResolver;
use Closure;
use Generator;
use Throwable;

/**
 * Executes one operation across the candidate list.
 *
 * @template T of Model
 *
 * @internal
 */
final class FailoverRunner
{
    /**
     * @param  list<Model>  $models
     * @param  class-string<T>  $contract
     * @param  Closure(Throwable): bool  $retryWhen
     * @param  list<Closure(FailoverEvent): void>  $listeners
     */
    public function __construct(
        private readonly array $models,
        private readonly string $contract,
        private readonly Closure $retryWhen,
        private readonly array $listeners,
        private readonly int $cooldownSeconds,
        private readonly FailoverState $state,
    ) {}

    /**
     * Run a request/response operation with failover.
     *
     * @template TResult
     *
     * @param  Closure(T): TResult  $call
     * @return TResult
     */
    public function run(string $operation, Closure $call): mixed
    {
        $candidates = $this->candidates();
        $last = null;

        foreach ($candidates as $index => $model) {
            try {
                $adapter = $this->adapter($model);
            } catch (NoSuchModelException|InvalidArgumentException $error) {
                // This candidate has no adapter for the capability: skip it.
                $last = $error;
                $this->failed($operation, $model, $error, $index, $candidates, skip: true);

                continue;
            }

            try {
                $result = $call($adapter);
                unset($this->state->coolingUntil[$this->key($model)]);

                return $result;
            } catch (Throwable $error) {
                $last = $error;
                $this->failed($operation, $model, $error, $index, $candidates);
            }
        }

        throw $last ?? new InvalidArgumentException('Failover has no models to try.');
    }

    /**
     * Run a streaming operation. Failover happens only before the first part.
     *
     * @template TPart
     *
     * @param  Closure(T): Generator<int, TPart>  $call
     * @return Generator<int, TPart>
     */
    public function stream(string $operation, Closure $call): Generator
    {
        $candidates = $this->candidates();
        $last = null;

        foreach ($candidates as $index => $model) {
            $started = false;

            try {
                $adapter = $this->adapter($model);
            } catch (NoSuchModelException|InvalidArgumentException $error) {
                $last = $error;
                $this->failed($operation, $model, $error, $index, $candidates, skip: true);

                continue;
            }

            try {
                foreach ($call($adapter) as $part) {
                    $started = true;

                    yield $part;
                }

                unset($this->state->coolingUntil[$this->key($model)]);

                return;
            } catch (Throwable $error) {
                if ($started) {
                    throw $error;
                }

                $last = $error;
                $this->failed($operation, $model, $error, $index, $candidates);
            }
        }

        throw $last ?? new InvalidArgumentException('Failover has no models to try.');
    }

    /**
     * The adapter of the candidate that created a job, for follow-up calls.
     */
    public function adapterFor(string $provider, string $modelId): Model
    {
        foreach ($this->models as $model) {
            if ($model->provider() === $provider && $model->modelId() === $modelId) {
                return $this->adapter($model);
            }
        }

        throw new InvalidArgumentException("No failover candidate matches [{$provider}/{$modelId}].");
    }

    /**
     * @return T
     */
    private function adapter(Model $model): Model
    {
        return ModelResolver::resolve($model, $this->contract, 'Failover');
    }

    /**
     * @param  list<Model>  $candidates
     */
    private function failed(string $operation, Model $model, Throwable $error, int $index, array $candidates, bool $skip = false): void
    {
        $next = $candidates[$index + 1] ?? null;
        $retry = $skip || ($this->retryWhen)($error);

        if ($next === null || ! $retry) {
            if ($this->cooldownSeconds > 0 && $retry && ! $skip) {
                $this->state->coolingUntil[$this->key($model)] = hrtime(true) / 1e9 + $this->cooldownSeconds;
            }

            throw $error;
        }

        if ($this->cooldownSeconds > 0 && ! $skip) {
            $this->state->coolingUntil[$this->key($model)] = hrtime(true) / 1e9 + $this->cooldownSeconds;
        }

        $event = new FailoverEvent($operation, $model->provider(), $model->modelId(), $error, $index + 1, $next->provider(), $next->modelId());

        foreach ($this->listeners as $listener) {
            $listener($event);
        }
    }

    /**
     * Models that are not cooling down, in order; if every model is cooling
     * down, all of them are tried again.
     *
     * @return list<Model>
     */
    private function candidates(): array
    {
        if ($this->cooldownSeconds <= 0) {
            return $this->models;
        }

        $now = hrtime(true) / 1e9;
        $ready = array_values(array_filter(
            $this->models,
            fn(Model $model): bool => ($this->state->coolingUntil[$this->key($model)] ?? 0.0) <= $now,
        ));

        return $ready === [] ? $this->models : $ready;
    }

    private function key(Model $model): string
    {
        return $model->provider() . '/' . $model->modelId();
    }
}
