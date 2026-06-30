<?php

declare(strict_types=1);

namespace AiSdk\Support\Concerns;

use AiSdk\Capability;
use AiSdk\Contracts\BaseProvider;
use AiSdk\ModelDefinition;

trait RegistersModels
{
    abstract public static function default(): BaseProvider;

    /**
     * @param  array<int, Capability>  $capabilities
     */
    public static function registerModel(ModelDefinition|string $model, array $capabilities = []): BaseProvider
    {
        return self::default()->registerModel($model, $capabilities);
    }
}
