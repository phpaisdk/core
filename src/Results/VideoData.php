<?php

declare(strict_types=1);

namespace AiSdk\Results;

use AiSdk\Exceptions\VideoDataException;

final class VideoData
{
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?string $data = null,
        public readonly string $mimeType = 'video/mp4',
        public readonly ?float $duration = null,
        public readonly ?string $resolution = null,
    ) {}

    public function save(string $path): string
    {
        if ($this->data === null) {
            throw new VideoDataException(
                message: 'Video data has no binary payload to save; fetch the URL first.',
                context: ['url' => $this->url],
            );
        }

        if (file_put_contents($path, $this->data) === false) {
            throw new VideoDataException(
                message: "Unable to write video data to [{$path}].",
                context: ['path' => $path],
            );
        }

        return $path;
    }
}
