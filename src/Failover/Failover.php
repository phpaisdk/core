<?php

declare(strict_types=1);

namespace AiSdk\Failover;

use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\Model;
use AiSdk\Contracts\ResolvableModelInterface;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Contracts\TranscriptionModelInterface;
use AiSdk\Contracts\VideoModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use Closure;

/**
 * A model that tries several models in order and moves on when one is
 * unavailable (rate limited, overloaded, timing out, erroring).
 *
 *   Generate::text('Hi')->model(Failover::of(
 *       Anthropic::model('claude-sonnet-5-5'),
 *       OpenAI::model('gpt-5.5'),
 *   ))->run();
 *
 * It works for every capability: each candidate is resolved to the adapter for
 * the operation that runs, and candidates that cannot perform it are skipped.
 * Streaming only fails over before the first part is produced.
 */
final class Failover implements ResolvableModelInterface
{
    /** @var list<Model> */
    private array $models;

    /** @var Closure(\Throwable): bool */
    private Closure $retryWhen;

    /** @var list<Closure(FailoverEvent): void> */
    private array $listeners = [];

    private int $cooldownSeconds = 0;

    private FailoverState $state;

    /**
     * @param  list<Model>  $models
     */
    private function __construct(array $models)
    {
        $this->models = $models;
        $this->state = new FailoverState();
        $this->retryWhen = FailoverPolicy::isTransient(...);
    }

    public static function of(Model ...$models): self
    {
        $models = array_values($models);

        if (count($models) < 2) {
            throw new InvalidArgumentException('Failover::of() needs at least two models.');
        }

        return new self($models);
    }

    /**
     * Replace the rule deciding which errors move on to the next model.
     *
     * @param  callable(\Throwable): bool  $decide
     */
    public function retryWhen(callable $decide): self
    {
        $this->retryWhen = Closure::fromCallable($decide);

        return $this;
    }

    /**
     * Skip a model that just failed for this many seconds (per process), so a
     * provider outage does not add latency to every call.
     */
    public function cooldown(int $seconds): self
    {
        $this->cooldownSeconds = max(0, $seconds);

        return $this;
    }

    /**
     * @param  callable(FailoverEvent): void  $listener
     */
    public function onFailover(callable $listener): self
    {
        $this->listeners[] = Closure::fromCallable($listener);

        return $this;
    }

    public function specificationVersion(): string
    {
        return 'v1';
    }

    public function provider(): string
    {
        return 'failover';
    }

    public function modelId(): string
    {
        return implode(',', array_map(static fn(Model $model): string => $model->provider() . '/' . $model->modelId(), $this->models));
    }

    public function resolve(string $contract): Model
    {
        return match ($contract) {
            TextModelInterface::class => new FailoverTextModel($this->runner(TextModelInterface::class)),
            ImageModelInterface::class => new FailoverImageModel($this->runner(ImageModelInterface::class)),
            SpeechModelInterface::class => new FailoverSpeechModel($this->runner(SpeechModelInterface::class)),
            TranscriptionModelInterface::class => new FailoverTranscriptionModel($this->runner(TranscriptionModelInterface::class)),
            EmbeddingModelInterface::class => new FailoverEmbeddingModel($this->runner(EmbeddingModelInterface::class)),
            VideoModelInterface::class => new FailoverVideoModel($this->runner(VideoModelInterface::class)),
            default => throw new InvalidArgumentException("Failover does not support [{$contract}]."),
        };
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $contract
     * @return FailoverRunner<T>
     */
    private function runner(string $contract): FailoverRunner
    {
        return new FailoverRunner($this->models, $contract, $this->retryWhen, $this->listeners, $this->cooldownSeconds, $this->state);
    }
}
