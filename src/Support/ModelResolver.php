<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Capability;
use AiSdk\Contracts\Model;
use AiSdk\Exceptions\CapabilityNotSupportedException;
use AiSdk\Exceptions\InvalidArgumentException;

/**
 * Resolves and capability-gates a model in one call. Removes the
 * requireModel()/requireSupportedModel() boilerplate duplicated across every
 * pending request (and the redundant repeated calls within a single run()).
 */
final class ModelResolver
{
    /**
     * @template T of Model
     *
     * @param  T|null  $model
     * @param  class-string<T>  $expected
     * @param  array<int, Capability>  $required
     * @return T
     */
    public static function resolve(?Model $model, string $expected, string $method, array $required = []): Model
    {
        if (! $model instanceof $expected) {
            throw new InvalidArgumentException(
                "{$method} requires a model of type [{$expected}]. Call ->model(...) or Generate::model(...).",
            );
        }

        foreach ($required as $capability) {
            $support = $model->capability($capability);
            if (! $support->isSupported()) {
                throw CapabilityNotSupportedException::fromSupport($model->provider(), $model->modelId(), $support);
            }
        }

        return $model;
    }
}
