<?php

declare(strict_types=1);

namespace AiSdk\Results;

use AiSdk\Exceptions\AiSdkException;

final class ImageData
{
    public function __construct(
        public readonly ?string $base64 = null,
        public readonly string $mimeType = 'image/png',
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $url = null,
    ) {}

    public function bytes(): ?string
    {
        if ($this->base64 === null) {
            return null;
        }

        $bytes = base64_decode($this->base64, strict: true);

        return $bytes === false ? null : $bytes;
    }

    public function save(string $path): string
    {
        $bytes = $this->bytes();
        if ($bytes === null) {
            throw new AiSdkException('Image data does not contain decodable binary content.');
        }

        if (file_put_contents($path, $bytes) === false) {
            throw new AiSdkException("Unable to write image data to [{$path}].");
        }

        return $path;
    }
}
