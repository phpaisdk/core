<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class TransportFrame
{
    public function __construct(public TransportFrameType $type, public string $payload) {}

    public static function text(string $payload): self
    {
        return new self(TransportFrameType::Text, $payload);
    }

    public static function binary(string $payload): self
    {
        return new self(TransportFrameType::Binary, $payload);
    }
}
