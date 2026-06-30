<?php

declare(strict_types=1);

namespace AiSdk\Responses\Parts;

final class ReasoningPart extends ResponsePart
{
    public function __construct(public readonly string $text) {}
}
