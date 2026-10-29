<?php

declare(strict_types=1);

namespace AiSdk\Decisions;

use AiSdk\Content;
use AiSdk\Exceptions\InvalidArgumentException;

final readonly class Question
{
    /**
     * @param string|list<Content> $instructions
     * @param array<array-key, string> $choices
     * @param list<string> $levels
     */
    private function __construct(
        public string $name,
        public string|array $instructions,
        public string $kind,
        public array $choices = [],
        public array $levels = [],
    ) {
        if ($name === '' || $instructions === '' || $instructions === []) {
            throw new InvalidArgumentException('Decision questions need a name and instructions.');
        }
        if (is_array($instructions)) {
            foreach ($instructions as $part) {
                if (! $part instanceof Content || ! in_array($part->type, [Content::TYPE_TEXT, Content::TYPE_IMAGE], true) || $part->fileReference() !== null) {
                    throw new InvalidArgumentException('Question instructions support text and inline or URL images only.');
                }
            }
        }
    }

    /** @param string|list<Content> $instructions */
    public static function predicate(string $name, string|array $instructions): self
    {
        return new self($name, $instructions, 'predicate');
    }

    /**
     * @param string|list<Content> $instructions
     * @param array<array-key, string> $choices Option => description.
     */
    public static function choice(string $name, string|array $instructions, array $choices): self
    {
        if (count($choices) < 2) {
            throw new InvalidArgumentException('Choice questions need at least two options.');
        }
        foreach ($choices as $value => $description) {
            // PHP stores numeric-string keys such as "42" as integers.
            if ((string) $value === '' || ! is_string($description)) {
                throw new InvalidArgumentException('Choices require nonempty string keys and string descriptions.');
            }
        }

        return new self($name, $instructions, 'choice', choices: $choices);
    }

    /**
     * @param string|list<Content> $instructions
     * @param list<string> $levels Ordered from lowest to highest.
     */
    public static function score(string $name, string|array $instructions, array $levels): self
    {
        return new self($name, $instructions, 'score', levels: self::validatedLevels($levels));
    }

    /**
     * Validate the runtime array boundary, including untyped callers.
     * @param array<array-key, mixed> $levels
     * @return list<string>
     */
    private static function validatedLevels(array $levels): array
    {
        if (count($levels) < 2 || ! array_is_list($levels)) {
            throw new InvalidArgumentException('Score questions need an ordered list of at least two levels.');
        }
        $validated = [];
        foreach ($levels as $level) {
            if (! is_string($level) || $level === '') {
                throw new InvalidArgumentException('Score levels must be nonempty strings.');
            }
            $validated[] = $level;
        }

        return $validated;
    }
}
