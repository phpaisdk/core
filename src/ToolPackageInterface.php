<?php

declare(strict_types=1);

namespace AiSdk;

interface ToolPackageInterface
{
    /**
     * @return iterable<mixed>
     */
    public function tools(): iterable;
}
