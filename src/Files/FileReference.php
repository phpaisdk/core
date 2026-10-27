<?php

declare(strict_types=1);

namespace AiSdk\Files;

use AiSdk\Exceptions\InvalidArgumentException;

/** Provider identity is not an authorization boundary; applications own user/file mappings. */
final readonly class FileReference
{
    public function __construct(public string $provider, public string $id)
    {
        if ($provider === '' || $id === '' || in_array($id, ['.', '..'], true) || preg_match('/[\x00-\x1f\x7f]/', $id)) {
            throw new InvalidArgumentException('A stored file requires a provider and a nonempty, control-free id.');
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
