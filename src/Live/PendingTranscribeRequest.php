<?php

declare(strict_types=1);

namespace AiSdk\Live;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Live\Contracts\LiveClientSecretModelInterface;
use AiSdk\Live\Contracts\LiveWebRtcModelInterface;

final class PendingTranscribeRequest extends PendingLiveRequest
{
    protected function operation(): LiveOperation
    {
        return LiveOperation::Transcribe;
    }

    public function language(string $language): static
    {
        return $this->option('language', $language);
    }

    public function audioFormat(string $format): static
    {
        return $this->option('input_audio_format', $format);
    }

    public function clientSecret(): ClientSecret
    {
        $model = $this->liveModel();

        if (! $model instanceof LiveClientSecretModelInterface) {
            throw new InvalidArgumentException('The selected Live transcription model does not support client secrets.');
        }

        return $model->clientSecret($this->request());
    }

    public function webRtc(string $offerSdp): WebRtcAnswer
    {
        if (trim($offerSdp) === '') {
            throw new InvalidArgumentException('Live::transcribe()->webRtc() requires an SDP offer.');
        }

        $model = $this->liveModel();

        if (! $model instanceof LiveWebRtcModelInterface) {
            throw new InvalidArgumentException('The selected Live transcription model does not support WebRTC.');
        }

        return $model->webRtc($this->request(), $offerSdp);
    }
}
