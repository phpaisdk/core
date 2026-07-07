<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Requests\ImageRequest;
use AiSdk\Responses\ImageResponse;

interface ImageModelInterface extends Model
{
    public function generate(ImageRequest $request): ImageResponse;
}
