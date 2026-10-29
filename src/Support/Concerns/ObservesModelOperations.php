<?php

declare(strict_types=1);

namespace AiSdk\Support\Concerns;

use AiSdk\Contracts\Model;
use AiSdk\Observability\ModelEvent;
use AiSdk\Observability\ModelObservation;
use Closure;

trait ObservesModelOperations
{
    /** @var Closure(ModelEvent): void|null */
    private ?Closure $observer = null;

    /** @param callable(ModelEvent): void $observer */
    public function observe(callable $observer): self
    {
        $this->observer = Closure::fromCallable($observer);

        return $this;
    }

    /**
     * @template T
     * @param Closure(): T $call
     * @return T
     */
    private function observeOperation(Model $model, string $operation, Closure $call): mixed
    {
        return (new ModelObservation($this->observer))->call($model, $operation, $call);
    }
}
