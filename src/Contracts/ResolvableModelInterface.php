<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

/**
 * Internal capability resolver used by the public provider model reference.
 *
 * @internal Provider packages implement this through BaseProvider.
 */
interface ResolvableModelInterface extends Model
{
    /** @param class-string<Model> $contract */
    public function resolve(string $contract): Model;
}
