<?php

declare(strict_types=1);

namespace AiSdk\Tests\Fakes;

use AiSdk\Capability;
use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Requests\ImageRequest;
use AiSdk\Responses\ImageResponse;
use AiSdk\Results\ImageData;

final class FakeImageModel extends BaseModel implements ImageModelInterface
{
    public ?ImageRequest $lastRequest = null;

    /**
     * @param  array<int, Capability>  $capabilities
     */
    public function __construct(
        private readonly ImageResponse $response,
        private readonly array $capabilities = [Capability::ImageGeneration],
    ) {}

    public function provider(): string
    {
        return 'fake';
    }

    public function modelId(): string
    {
        return 'fake-image-1';
    }

    /**
     * @return array<int, Capability>
     */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function generate(ImageRequest $request): ImageResponse
    {
        $this->lastRequest = $request;

        return $this->response;
    }

    public static function image(?string $base64 = 'aW1hZ2U='): self
    {
        return new self(new ImageResponse([
            new ImageData(base64: $base64, mimeType: 'image/png'),
        ]));
    }

    public static function withoutImageGeneration(): self
    {
        return new self(new ImageResponse([]), capabilities: []);
    }
}
