<?php

declare(strict_types=1);

namespace AiSdk\Streaming;

final class ErrorPart extends StreamPart
{
    public function __construct(public readonly \Throwable $error) {}
}
