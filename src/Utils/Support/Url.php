<?php

declare(strict_types=1);

namespace AiSdk\Utils\Support;

final class Url
{
    public static function withoutTrailingSlash(string $url): string
    {
        return rtrim($url, '/');
    }

    public static function joinPath(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public static function dataUrl(string $base64, string $mimeType): string
    {
        return "data:{$mimeType};base64,{$base64}";
    }
}
