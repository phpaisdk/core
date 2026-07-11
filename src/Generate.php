<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\Model;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Support\PendingImageRequest;
use AiSdk\Support\PendingSpeechRequest;
use AiSdk\Support\PendingTextRequest;
use AiSdk\Support\Sdk;
use AiSdk\Support\SdkFactory;

/**
 * PHP-native generation facade and runtime holder.
 */
final class Generate
{
    private static ?Sdk $sdk = null;
    private static ?SdkFactory $runtimeFactory = null;

    private static function factory(): SdkFactory
    {
        return new SdkFactory();
    }

    public static function configure(Sdk $sdk): void
    {
        self::$sdk = $sdk;
    }

    public static function sdk(): Sdk
    {
        return self::$sdk ??= self::runtimeFactory()->make();
    }

    public static function reset(): void
    {
        self::$sdk = null;
        self::$runtimeFactory = null;
    }

    public static function model(Model $model): void
    {
        self::$runtimeFactory ??= self::factory();
        self::$runtimeFactory->withDefaultModel($model);

        if (self::$sdk !== null) {
            self::$sdk = self::$sdk->withDefaultModel($model);
        }
    }

    public static function text(?string $prompt = null): PendingTextRequest
    {
        $request = new PendingTextRequest(self::defaultTextModel(), self::sdk()->container);

        return $prompt === null ? $request : $request->prompt($prompt);
    }

    public static function image(?string $prompt = null): PendingImageRequest
    {
        $request = new PendingImageRequest(self::defaultImageModel());

        return $prompt === null ? $request : $request->prompt($prompt);
    }

    public static function speech(?string $input = null): PendingSpeechRequest
    {
        $request = new PendingSpeechRequest(self::defaultSpeechModel());

        return $input === null ? $request : $request->input($input);
    }

    private static function runtimeFactory(): SdkFactory
    {
        return self::$runtimeFactory ??= self::factory();
    }

    private static function defaultTextModel(): ?TextModelInterface
    {
        $model = self::$sdk !== null
            ? self::$sdk->defaultTextModel ?? self::$sdk->defaultModel
            : self::$runtimeFactory?->defaultTextModel() ?? self::$runtimeFactory?->defaultModel();

        return $model instanceof TextModelInterface ? $model : null;
    }

    private static function defaultImageModel(): ?ImageModelInterface
    {
        $model = self::$sdk !== null
            ? self::$sdk->defaultImageModel ?? self::$sdk->defaultModel
            : self::$runtimeFactory?->defaultImageModel() ?? self::$runtimeFactory?->defaultModel();

        return $model instanceof ImageModelInterface ? $model : null;
    }

    private static function defaultSpeechModel(): ?SpeechModelInterface
    {
        $model = self::$sdk !== null
            ? self::$sdk->defaultSpeechModel ?? self::$sdk->defaultModel
            : self::$runtimeFactory?->defaultSpeechModel() ?? self::$runtimeFactory?->defaultModel();

        return $model instanceof SpeechModelInterface ? $model : null;
    }
}
