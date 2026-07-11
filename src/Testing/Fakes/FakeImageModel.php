<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Requests\ImageRequest;
use AiSdk\Responses\ImageResponse;
use AiSdk\Results\ImageData;

final class FakeImageModel extends BaseModel implements ImageModelInterface
{
    public ?ImageRequest $lastRequest = null;

    public function __construct(private readonly ImageResponse $response) {}

    public function provider(): string
    {
        return 'fake';
    }

    public function modelId(): string
    {
        return 'fake-image-1';
    }

    public function generate(ImageRequest $request): ImageResponse
    {
        $this->lastRequest = $request;

        return $this->response;
    }

    public static function image(string $base64 = 'aW1hZ2U=', string $mimeType = 'image/png'): self
    {
        return new self(new ImageResponse([
            new ImageData(base64: $base64, mimeType: $mimeType),
        ]));
    }
}
