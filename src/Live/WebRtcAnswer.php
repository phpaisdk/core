<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class WebRtcAnswer
{
    /** @param array<string, mixed> $raw */
    public function __construct(public string $sdp, public ?string $callId = null, public array $raw = []) {}
}
