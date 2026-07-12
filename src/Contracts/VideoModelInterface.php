<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Requests\VideoRequest;
use AiSdk\Responses\VideoJob;

interface VideoModelInterface extends Model
{
    public function generate(VideoRequest $request): VideoJob;

    public function poll(VideoJob $job): VideoJob;
}
