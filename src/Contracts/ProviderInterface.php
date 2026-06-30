<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

/**
 * A provider exposes model factories per modality. Modalities are added as
 * slices land; unsupported modalities throw NoSuchModelException (see BaseProvider).
 */
interface ProviderInterface
{
    public function name(): string;

    public function textModel(string $modelId): TextModelInterface;
}
