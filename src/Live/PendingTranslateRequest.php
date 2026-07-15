<?php

declare(strict_types=1);

namespace AiSdk\Live;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Live\Contracts\LiveClientSecretModelInterface;
use AiSdk\Live\Contracts\LiveWebRtcModelInterface;

final class PendingTranslateRequest extends PendingLiveRequest
{
    protected function operation(): LiveOperation
    {
        return LiveOperation::Translate;
    }

    public function from(string $language): static
    {
        return $this->option('from', $language);
    }
    public function to(string $language): static
    {
        return $this->option('to', $language);
    }

    public function inputAudioFormat(string $format): static
    {
        return $this->option('input_audio_format', $format);
    }

    public function outputAudioFormat(string $format): static
    {
        return $this->option('output_audio_format', $format);
    }

    public function clientSecret(): ClientSecret
    {
        $model = $this->liveModel();

        if (! $model instanceof LiveClientSecretModelInterface) {
            throw new InvalidArgumentException('The selected Live translation model does not support client secrets.');
        }

        return $model->clientSecret($this->request());
    }

    public function webRtc(string $offerSdp): WebRtcAnswer
    {
        if (trim($offerSdp) === '') {
            throw new InvalidArgumentException('Live::translate()->webRtc() requires an SDP offer.');
        }

        $model = $this->liveModel();

        if (! $model instanceof LiveWebRtcModelInterface) {
            throw new InvalidArgumentException('The selected Live translation model does not support WebRTC.');
        }

        return $model->webRtc($this->request(), $offerSdp);
    }

    protected function request(): LiveRequest
    {
        if (! is_string($this->options['to'] ?? null)) {
            throw new InvalidArgumentException('Live::translate() requires to().');
        }

        return parent::request();
    }
}
