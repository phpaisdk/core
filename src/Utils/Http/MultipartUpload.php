<?php

declare(strict_types=1);

namespace AiSdk\Utils\Http;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Files\FileUpload;
use AiSdk\Support\Sdk;
use GuzzleHttp\Psr7\MultipartStream;
use Psr\Http\Message\RequestInterface;

/** Streams multipart bodies instead of copying uploaded files into a string. */
final class MultipartUpload
{
    /** @param array<string, scalar> $fields */
    public static function request(Sdk $sdk, string $url, FileUpload $file, array $fields, int $maxBytes): RequestInterface
    {
        $parts = [];
        foreach ($fields as $name => $value) {
            if ($name === 'file' || preg_match('/[\x00-\x1f\x7f"\\\\]/', $name)) {
                throw new InvalidArgumentException('Invalid multipart field name.');
            }
            $parts[] = ['name' => $name, 'contents' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value];
        }
        $parts[] = ['name' => 'file', 'filename' => $file->filename, 'contents' => $file->stream($sdk->streamFactory, $maxBytes), 'headers' => ['Content-Type' => $file->mimeType]];
        $body = new MultipartStream($parts);

        return $sdk->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $body->getBoundary())
            ->withBody($body);
    }
}
