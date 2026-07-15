<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Contracts\Model;
use AiSdk\Contracts\ResolvableModelInterface;
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
     * @param  Model|null  $model
     * @param  class-string<T>  $expected
     * @return T
     */
    public static function resolve(
        ?Model $model,
        string $expected,
        string $method,
        ?string $defaultSelector = 'Generate::model(...)',
    ): Model {
        if ($model instanceof $expected) {
            return $model;
        }

        if ($model instanceof ResolvableModelInterface) {
            $resolved = $model->resolve($expected);

            if ($resolved instanceof $expected) {
                return $resolved;
            }
        }

        if (! $model instanceof $expected) {
            $hint = 'Call ->model(...)';
            if ($defaultSelector !== null) {
                $hint .= " or {$defaultSelector}";
            }

            throw new InvalidArgumentException(
                "{$method} requires a model of type [{$expected}]. {$hint}.",
            );
        }

        return $model;
    }
}
