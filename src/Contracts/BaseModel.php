<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Capability;
use AiSdk\CapabilitySupport;
use AiSdk\Exceptions\CapabilityNotSupportedException;
use AiSdk\Generate;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Support\Sdk;
use AiSdk\Utils\Http\HttpRunner;

/**
 * Shared scaffolding for provider models.
 */
abstract class BaseModel implements Model
{
    protected const string SPEC_VERSION = 'v1';

    public function specificationVersion(): string
    {
        return static::SPEC_VERSION;
    }

    /**
     * Resolve the HTTP runner from the optional per-provider SDK override,
     * falling back to the globally configured runtime.
     */
    protected function runner(?Sdk $sdk = null): HttpRunner
    {
        return HttpRunner::fromSdk($sdk ?? Generate::sdk());
    }

    /**
     * @param  array<int, Capability>  $adapterCapabilities
     * @param  array<string, string>  $adaptedCapabilities
     */
    protected function ensureTextRequestSupported(
        TextModelRequest $request,
        array $adapterCapabilities,
        array $adaptedCapabilities = [],
        bool $streaming = false,
    ): void {
        $required = [Capability::TextGeneration];

        if ($streaming) {
            $required[] = Capability::Streaming;
        }

        if ($request->output !== null) {
            $required[] = Capability::StructuredOutput;
        }

        if ($request->tools !== []) {
            $required[] = Capability::ToolCalling;
        }

        if ($request->reasoning !== null) {
            $required[] = Capability::Reasoning;
        }

        foreach ($request->messages as $message) {
            foreach ($message->content as $content) {
                if (($file = $content->fileReference()) !== null) {
                    $file->forProvider($this->provider());
                    if (! $this->supportsStoredFiles()) {
                        throw new \AiSdk\Exceptions\InvalidArgumentException('This model adapter does not support stored-file references.');
                    }
                }
                $capability = match ($content->type) {
                    \AiSdk\Content::TYPE_IMAGE => Capability::ImageInput,
                    \AiSdk\Content::TYPE_AUDIO => Capability::AudioInput,
                    \AiSdk\Content::TYPE_VIDEO => Capability::VideoInput,
                    \AiSdk\Content::TYPE_FILE => Capability::FileInput,
                    default => null,
                };

                if ($capability !== null) {
                    $required[] = $capability;
                }
            }
        }

        foreach (array_unique($required, SORT_REGULAR) as $capability) {
            $this->ensureAdapterCapability($capability, $adapterCapabilities, $adaptedCapabilities);
        }
    }

    protected function supportsStoredFiles(): bool
    {
        return false;
    }

    /**
     * @param  array<string, string>  $adaptedCapabilities
     */
    protected function usesAdaptedCapability(Capability $capability, array $adaptedCapabilities): bool
    {
        return isset($adaptedCapabilities[$capability->name()]);
    }

    /**
     * @param  array<int, Capability>  $adapterCapabilities
     * @param  array<string, string>  $adaptedCapabilities
     */
    private function ensureAdapterCapability(
        Capability $capability,
        array $adapterCapabilities,
        array $adaptedCapabilities,
    ): void {
        if (in_array($capability, $adapterCapabilities, true) || $this->usesAdaptedCapability($capability, $adaptedCapabilities)) {
            return;
        }

        $support = CapabilitySupport::notSupported(
            $capability,
            "Provider adapter [{$this->provider()}] does not implement capability [{$capability->name()}].",
        );

        throw CapabilityNotSupportedException::fromSupport($this->provider(), $this->modelId(), $support);
    }
}
