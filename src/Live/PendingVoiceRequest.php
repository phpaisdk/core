<?php

declare(strict_types=1);

namespace AiSdk\Live;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Live\Contracts\LiveCallModelInterface;
use AiSdk\Live\Contracts\LiveClientSecretModelInterface;
use AiSdk\Live\Contracts\LiveWebRtcModelInterface;
use AiSdk\Tool;

final class PendingVoiceRequest extends PendingLiveRequest
{
    protected function operation(): LiveOperation
    {
        return LiveOperation::Voice;
    }

    public function instructions(string $instructions): static
    {
        return $this->option('instructions', $instructions);
    }

    /** @param list<Tool> $tools */
    public function tools(array $tools): static
    {
        $names = [];
        foreach ($tools as $tool) {
            if (! $tool instanceof Tool) {
                throw new InvalidArgumentException('Live voice tools must be Tool instances.');
            }

            $name = $this->validatedToolName($tool);
            if (isset($names[$name])) {
                throw new InvalidArgumentException("Duplicate tool name [{$name}].");
            }

            $names[$name] = true;
        }

        $this->tools = array_values($tools);

        return $this;
    }

    public function tool(Tool $tool): static
    {
        $name = $this->validatedToolName($tool);
        foreach ($this->tools as $existing) {
            if ($existing->name() === $name) {
                throw new InvalidArgumentException("Duplicate tool name [{$name}].");
            }
        }

        $this->tools[] = $tool;

        return $this;
    }

    public function voice(string $voice): static
    {
        return $this->option('voice', $voice);
    }

    /** @param array<string, mixed> $turnDetection */
    public function turnDetection(string|array|null $turnDetection): static
    {
        return $this->option('turn_detection', $turnDetection);
    }

    public function language(string $language): static
    {
        return $this->option('language', $language);
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
            throw new InvalidArgumentException('The selected Live model does not support client secrets.');
        }

        return $model->clientSecret($this->request());
    }

    public function webRtc(string $offerSdp): WebRtcAnswer
    {
        if (trim($offerSdp) === '') {
            throw new InvalidArgumentException('Live::voice()->webRtc() requires an SDP offer.');
        }

        $model = $this->liveModel();

        if (! $model instanceof LiveWebRtcModelInterface) {
            throw new InvalidArgumentException('The selected Live model does not support WebRTC.');
        }

        return $model->webRtc($this->request(), $offerSdp);
    }

    public function call(string $callId): LiveCall
    {
        if (trim($callId) === '') {
            throw new InvalidArgumentException('Live::voice()->call() requires a call ID.');
        }

        $model = $this->liveModel();

        if (! $model instanceof LiveCallModelInterface) {
            throw new InvalidArgumentException('The selected Live model does not support provider-hosted calls.');
        }

        return new LiveCall($model->call($this->request(), $callId), $this->tools);
    }

    private function validatedToolName(Tool $tool): string
    {
        $name = $tool->name();
        if ($name === '') {
            throw new InvalidArgumentException('Tools must have a name. Call ->as(...) or pass a name to Tool::make().');
        }

        return $name;
    }
}
