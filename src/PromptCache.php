<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Exceptions\InvalidArgumentException;

/**
 * A portable hint that the stable beginning of a prompt (instructions, tools
 * and earlier messages) should be cached by the provider.
 *
 *   Generate::text($prompt)->instructions($longGuide)->cache()                 // sensible default
 *   Generate::text($prompt)->cache(PromptCache::ttl('1h'))                      // longer lived cache
 *   Generate::text($prompt)->cache(PromptCache::key('tenant-42'))               // routing key for implicit caches
 *
 * It is only a hint: providers that cache automatically ignore the markers,
 * and providers without prompt caching ignore it entirely. Read the effect
 * from Usage::$cachedInputTokens and Usage::$cacheWriteInputTokens.
 */
final readonly class PromptCache
{
    public const string TTL_5_MINUTES = '5m';
    public const string TTL_1_HOUR = '1h';

    private function __construct(
        public ?string $ttl = null,
        public ?string $key = null,
    ) {}

    /**
     * Cache instructions, tools and the conversation up to the latest message,
     * with the provider's default lifetime.
     */
    public static function auto(): self
    {
        return new self();
    }

    /**
     * @param  string  $ttl  '5m' or '1h'
     */
    public static function ttl(string $ttl): self
    {
        if (! in_array($ttl, [self::TTL_5_MINUTES, self::TTL_1_HOUR], true)) {
            throw new InvalidArgumentException("Prompt cache ttl must be '5m' or '1h', got [{$ttl}].");
        }

        return new self($ttl);
    }

    /**
     * A stable identifier used by providers that route cache lookups by key
     * (for example OpenAI's prompt_cache_key). Use one per tenant, user or document set.
     */
    public static function key(string $key): self
    {
        if ($key === '') {
            throw new InvalidArgumentException('A prompt cache key cannot be empty.');
        }

        return new self(key: $key);
    }

    public function withTtl(string $ttl): self
    {
        return new self(self::ttl($ttl)->ttl, $this->key);
    }

    public function withKey(string $key): self
    {
        return new self($this->ttl, self::key($key)->key);
    }
}
