<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Requests\SpeechRequest;
use AiSdk\Responses\SpeechResponse;

interface SpeechModelInterface extends Model
{
    public function generate(SpeechRequest $request): SpeechResponse;
}
