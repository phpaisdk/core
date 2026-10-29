<?php

declare(strict_types=1);

namespace AiSdk\Files;

use AiSdk\Exceptions\InvalidArgumentException;

/** Provider identity is not an authorization boundary; applications own user/file mappings. */
final readonly class FileReference
{
    public function __construct(public string $provider, public string $id)
    {
        // An id becomes one URL path segment. Separators, query/fragment characters and escapes could make a server
        // resolve a different route (OpenAI redirected `..%2Fmodels` to /v1/models), so they are never accepted.
        if ($provider === '' || $id === '' || strlen($id) > 512 || in_array($id, ['.', '..'], true) || preg_match('/[\x00-\x20\x7f\/\\\\?#%]/', $id)) {
            throw new InvalidArgumentException('A stored file requires a provider and a nonempty id without whitespace, control characters or any of / \\ ? # %.');
        }
    }

    public function forProvider(string $provider): string
    {
        if ($provider !== $this->provider) {
            throw new InvalidArgumentException('A stored file cannot be used with a different provider.');
        }

        return $this->id;
    }
}
