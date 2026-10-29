<?php

declare(strict_types=1);

namespace AiSdk\Conversations;

use AiSdk\Exceptions\ConversationException;

/** Portable source reference; the store neither fetches nor interprets its URI. */
final readonly class Citation
{
    public function __construct(public string $id, public ?string $title = null, public ?string $uri = null, public ?string $excerpt = null)
    {
        if ($id === '' || strlen($id) > 256 || strlen($title ?? '') > 4096 || strlen($uri ?? '') > 8192 || strlen($excerpt ?? '') > 65536) {
            throw new ConversationException('Citation fields exceed their bounds or have an empty id.');
        }
    }
}
