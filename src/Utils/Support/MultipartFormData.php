<?php

declare(strict_types=1);

namespace AiSdk\Utils\Support;

use AiSdk\Support\Json;

/** Provider-neutral multipart/form-data encoding for HTTP request bodies. */
final class MultipartFormData
{
    /**
     * @param  array<int, array{name: string, value: mixed, filename?: string, contentType?: string}>  $parts
     * @return array{body: string, boundary: string}
     */
    public static function encode(array $parts): array
    {
        $boundary = 'aisdk-' . bin2hex(random_bytes(16));
        $body = '';

        foreach ($parts as $part) {
            foreach (self::expand($part) as $expanded) {
                $name = self::quoted($expanded['name']);
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"{$name}\"";

                if (isset($expanded['filename'])) {
                    $filename = self::quoted($expanded['filename']);
                    $body .= "; filename=\"{$filename}\"";
                }

                $body .= "\r\n";

                if (isset($expanded['contentType'])) {
                    $body .= 'Content-Type: ' . self::headerValue($expanded['contentType']) . "\r\n";
                }

                $body .= "\r\n" . self::stringValue($expanded['value']) . "\r\n";
            }
        }

        $body .= "--{$boundary}--\r\n";

        return ['body' => $body, 'boundary' => $boundary];
    }

    /**
     * @param  array{name: string, value: mixed, filename?: string, contentType?: string}  $part
     * @return array<int, array{name: string, value: mixed, filename?: string, contentType?: string}>
     */
    private static function expand(array $part): array
    {
        if (! is_array($part['value']) || ! array_is_list($part['value'])) {
            return [$part];
        }

        $expanded = [];
        foreach ($part['value'] as $value) {
            $expanded[] = array_replace($part, ['value' => $value]);
        }

        return $expanded;
    }

    private static function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return Json::encode($value);
        }

        return (string) $value;
    }

    private static function quoted(string $value): string
    {
        return str_replace(["\r", "\n", '"'], ['', '', '\\"'], $value);
    }

    private static function headerValue(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }
}
