<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

interface Model
{
    public function specificationVersion(): string;

    public function provider(): string;

    public function modelId(): string;
}
