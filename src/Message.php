<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Exceptions\InvalidArgumentException;

final class Message
{
    public const string ROLE_SYSTEM = 'system';
    public const string ROLE_USER = 'user';
    public const string ROLE_ASSISTANT = 'assistant';
    public const string ROLE_TOOL = 'tool';

    /**
     * @param  array<int, Content>  $content
     * @param  array<int, ToolCall>  $toolCalls
     */
    private function __construct(
        public readonly string $role,
        public readonly array $content,
        public readonly ?string $name = null,
        public readonly ?string $toolCallId = null,
        public readonly array $toolCalls = [],
    ) {}

    public static function system(string $text): self
    {
        return new self(self::ROLE_SYSTEM, [Content::text($text)]);
    }

    /**
     * @param  string|array<int, Content>  $content
     */
    public static function user(string|array $content): self
    {
        return new self(self::ROLE_USER, self::normalize($content));
    }

    /**
     * @param  string|array<int, Content>  $content
     * @param  array<int, ToolCall>  $toolCalls
     */
    public static function assistant(string|array $content, array $toolCalls = []): self
    {
        return new self(self::ROLE_ASSISTANT, self::normalize($content), toolCalls: array_values($toolCalls));
    }

    public static function tool(string $toolCallId, string $output, ?string $name = null): self
    {
        return new self(
            role: self::ROLE_TOOL,
            content: [Content::text($output)],
            name: $name,
            toolCallId: $toolCallId,
        );
    }

    public function text(): string
    {
        $text = '';
        foreach ($this->content as $part) {
            $value = $part->textValue();
            if ($value !== null) {
                $text .= $value;
            }
        }

        return $text;
    }

    /**
     * @param  string|array<int, Content>  $content
     * @return array<int, Content>
     */
    private static function normalize(string|array $content): array
    {
        if (is_string($content)) {
            return [Content::text($content)];
        }

        foreach ($content as $part) {
            if (! $part instanceof Content) {
                throw new InvalidArgumentException('Message content must be an array of Content instances.');
            }
        }

        return array_values($content);
    }
}
