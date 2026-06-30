<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Exceptions\InvalidArgumentException;
use Stringable;

/**
 * A single typed input part for text generation.
 */
final class Content
{
    public const string TYPE_TEXT = 'text';
    public const string TYPE_IMAGE = 'image';
    public const string TYPE_AUDIO = 'audio';
    public const string TYPE_FILE = 'file';

    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public readonly string $type,
        public readonly array $payload,
    ) {}

    public static function text(string $text): self
    {
        return new self(self::TYPE_TEXT, [
            'source' => ContentSource::Text->value,
            'text' => $text,
        ]);
    }

    public static function image(string|Stringable $source, ?string $mimeType = null, ?string $filename = null, ?InputEncoding $encoding = null): self
    {
        return new self(self::TYPE_IMAGE, self::resolveSource((string) $source, $mimeType, $filename, $encoding));
    }

    public static function audio(string|Stringable $source, ?string $mimeType = null, ?string $filename = null, ?InputEncoding $encoding = null): self
    {
        return new self(self::TYPE_AUDIO, self::resolveSource((string) $source, $mimeType, $filename, $encoding));
    }

    public static function file(string|Stringable $source, ?string $mimeType = null, ?string $filename = null, ?InputEncoding $encoding = null): self
    {
        return new self(self::TYPE_FILE, self::resolveSource((string) $source, $mimeType, $filename, $encoding));
    }

    public function textValue(): ?string
    {
        return $this->type === self::TYPE_TEXT ? ($this->payload['text'] ?? null) : null;
    }

    public function data(): ?string
    {
        return $this->payload['data'] ?? null;
    }

    public function mimeType(): ?string
    {
        return $this->payload['mimeType'] ?? null;
    }

    public function url(): ?string
    {
        return $this->payload['url'] ?? null;
    }

    public function filename(): ?string
    {
        return $this->payload['filename'] ?? null;
    }

    public function source(): ContentSource
    {
        $source = $this->payload['source'] ?? ContentSource::Raw->value;

        return ContentSource::tryFrom((string) $source) ?? ContentSource::Raw;
    }

    public function base64Data(): ?string
    {
        if ($this->source() === ContentSource::Base64) {
            return $this->data();
        }

        if ($this->source() === ContentSource::DataUri && $this->data() !== null) {
            return self::base64FromDataUri($this->data());
        }

        if ($this->source() === ContentSource::Raw && $this->data() !== null) {
            return base64_encode($this->data());
        }

        return null;
    }

    public function dataUri(): ?string
    {
        if ($this->source() === ContentSource::DataUri) {
            return $this->data();
        }

        $base64 = $this->base64Data();
        $mimeType = $this->mimeType();

        return $base64 !== null && $mimeType !== null
            ? "data:{$mimeType};base64,{$base64}"
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveSource(string $source, ?string $mimeType, ?string $filename, ?InputEncoding $encoding): array
    {
        if (self::isHttpUrl($source)) {
            return [
                'source' => ContentSource::Url->value,
                'url' => $source,
                'mimeType' => $mimeType,
                'filename' => $filename ?? basename(parse_url($source, PHP_URL_PATH) ?: ''),
            ];
        }

        if (str_starts_with($source, 'data:')) {
            $parsed = self::parseDataUri($source);

            return [
                'source' => ContentSource::DataUri->value,
                'data' => $source,
                'mimeType' => $mimeType ?? $parsed['mimeType'],
                'filename' => $filename,
            ];
        }

        if ($encoding === InputEncoding::Base64) {
            if ($mimeType === null) {
                throw new InvalidArgumentException('Base64 content requires a MIME type.');
            }

            return [
                'source' => ContentSource::Base64->value,
                'data' => $source,
                'mimeType' => $mimeType,
                'filename' => $filename,
            ];
        }

        if (is_file($source) && is_readable($source)) {
            $data = file_get_contents($source);
            if ($data === false) {
                throw new InvalidArgumentException("Unable to read content path [{$source}].");
            }

            return [
                'source' => ContentSource::Raw->value,
                'data' => $data,
                'mimeType' => $mimeType ?? self::detectMimeType($source),
                'filename' => $filename ?? basename($source),
            ];
        }

        if ($mimeType === null) {
            throw new InvalidArgumentException('Raw content requires a MIME type.');
        }

        return [
            'source' => ContentSource::Raw->value,
            'data' => $source,
            'mimeType' => $mimeType,
            'filename' => $filename,
        ];
    }

    private static function isHttpUrl(string $source): bool
    {
        return filter_var($source, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($source, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    /**
     * @return array{mimeType: string|null}
     */
    private static function parseDataUri(string $source): array
    {
        if (! preg_match('/^data:([^;,]+)?(?:;base64)?,/i', $source, $matches)) {
            throw new InvalidArgumentException('Invalid data URI content.');
        }

        return ['mimeType' => ($matches[1] ?? '') !== '' ? $matches[1] : null];
    }

    private static function base64FromDataUri(string $source): ?string
    {
        if (! preg_match('/^data:[^,]*?(;base64)?,(.*)$/is', $source, $matches)) {
            return null;
        }

        $data = $matches[2];

        return $matches[1] === ';base64'
            ? $data
            : base64_encode(rawurldecode($data));
    }

    private static function detectMimeType(string $path): ?string
    {
        if (function_exists('mime_content_type')) {
            $type = mime_content_type($path);

            return is_string($type) ? $type : null;
        }

        return null;
    }
}
