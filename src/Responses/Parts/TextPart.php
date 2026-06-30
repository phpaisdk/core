<?php

declare(strict_types=1);

namespace AiSdk\Responses\Parts;

final class TextPart extends ResponsePart
{
    public function __construct(public readonly string $text) {}
}
