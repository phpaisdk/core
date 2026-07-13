<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Requests\TranscriptionRequest;
use AiSdk\Responses\TranscriptionResponse;

interface TranscriptionModelInterface extends Model
{
    public function transcribe(TranscriptionRequest $request): TranscriptionResponse;
}
