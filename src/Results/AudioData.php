<?php

declare(strict_types=1);

namespace AiSdk\Results;

use AiSdk\Exceptions\AiSdkException;

final class AudioData
{
    public function __construct(
        public readonly string $data = '',
        public readonly string $mimeType = 'audio/mpeg',
        public readonly ?float $duration = null,
    ) {}

    public function base64(): string
    {
        return base64_encode($this->data);
    }

    public function dataUri(): string
    {
        return 'data:' . $this->mimeType . ';base64,' . $this->base64();
    }

    public function save(string $path): string
    {
        if (file_put_contents($path, $this->data) === false) {
            throw new AiSdkException("Unable to write audio data to [{$path}].");
        }

        return $path;
    }
}
