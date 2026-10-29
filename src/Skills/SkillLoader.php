<?php

declare(strict_types=1);

namespace AiSdk\Skills;

use AiSdk\Exceptions\SkillException;
use DirectoryIterator;
use Throwable;

final readonly class SkillLoader
{
    /** @var list<string> */
    private array $roots;
    /** @var list<Skill> */
    private array $skills;

    /**
     * Roots contain SKILL.md directly, or immediate child directories containing SKILL.md.
     * @param list<string> $roots
     * @param list<Skill> $skills For application-supplied values; prefer fromSkills().
     */
    public function __construct(array $roots = [], public int $maxFileBytes = 262144, public int $maxFiles = 100, array $skills = [])
    {
        if ($maxFileBytes < 1 || $maxFileBytes > 16 * 1024 * 1024 || $maxFiles < 1 || $maxFiles > 1000 || count($roots) > 32 || ($roots !== [] && $skills !== [])) {
            throw new SkillException('Invalid skill loader bounds or mixed directory and inline skill sources.');
        }
        $canonical = [];
        foreach ($roots as $root) {
            if (! is_string($root) || str_contains($root, "\0")) {
                throw new SkillException('Skill roots must be application-chosen directories.');
            }
            $path = realpath($root);
            if ($path === false || ! is_dir($path)) {
                throw new SkillException('A skill root is unavailable.');
            }
            $canonical[] = $path;
        }
        $this->roots = array_values(array_unique($canonical));
        $this->skills = self::validateSkills($skills, $maxFiles, $maxFileBytes);
    }

    /** @param list<Skill> $skills */
    public static function fromSkills(array $skills, int $maxFileBytes = 262144, int $maxFiles = 100): self
    {
        return new self(maxFileBytes: $maxFileBytes, maxFiles: $maxFiles, skills: $skills);
    }

    /** @return list<Skill> */
    public function load(): array
    {
        if ($this->roots === []) {
            return $this->skills;
        }
        $skills = [];
        $visited = 0;

        try {
            foreach ($this->roots as $root) {
                if (is_file($root . '/SKILL.md') || is_link($root . '/SKILL.md')) {
                    if (count($skills) >= $this->maxFiles) {
                        throw new SkillException('Skill discovery exceeded its file limit.');
                    }
                    $skills[] = $this->parse($root);
                } else {
                    foreach (new DirectoryIterator($root) as $entry) {
                        if ($entry->isDot()) {
                            continue;
                        }
                        if (++$visited > 4096) {
                            throw new SkillException('Skill discovery exceeded its directory entry limit.');
                        }
                        if ($entry->isLink()) {
                            throw new SkillException('Symlinks are not allowed during skill discovery.');
                        }
                        if ($entry->isDir() && (is_file($entry->getPathname() . '/SKILL.md') || is_link($entry->getPathname() . '/SKILL.md'))) {
                            if (count($skills) >= $this->maxFiles) {
                                throw new SkillException('Skill discovery exceeded its file limit.');
                            }
                            $skills[] = $this->parse($entry->getPathname());
                            if (count($skills) > $this->maxFiles) {
                                throw new SkillException('Skill discovery exceeded its file limit.');
                            }
                        }
                    }
                }
                if (count($skills) > $this->maxFiles) {
                    throw new SkillException('Skill discovery exceeded its file limit.');
                }
            }
        } catch (SkillException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new SkillException('Skill discovery failed.', previous: $error);
        }
        $skills = self::validateSkills($skills, $this->maxFiles, $this->maxFileBytes);
        usort($skills, static fn(Skill $a, Skill $b): int => strcmp($a->name, $b->name));

        return $skills;
    }

    /** Freeze the advertised skill set, preserving its configured resource read limits. */
    public function snapshot(): self
    {
        return self::fromSkills($this->load(), $this->maxFileBytes, $this->maxFiles);
    }

    public function read(string $name, ?string $resource = null): string
    {
        foreach ($this->load() as $skill) {
            if ($skill->name === $name) {
                if ($resource === null) {
                    return $skill->body;
                }
                if ($skill->directory === null) {
                    throw new SkillException('Inline skills do not expose filesystem resources.');
                }

                return SkillReader::read($skill->directory, $resource, $this->maxFileBytes);
            }
        }

        throw new SkillException('The requested skill is unavailable.');
    }

    private function parse(string $directory): Skill
    {
        $text = SkillReader::read($directory, 'SKILL.md', $this->maxFileBytes);
        $text = str_replace("\r\n", "\n", $text);
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }
        if (! str_starts_with($text, "---\n") || ($end = strpos($text, "\n---\n", 4)) === false || $end > 16384) {
            throw new SkillException('Skills require bounded frontmatter delimited by --- lines.');
        }
        $fields = [];
        $lines = explode("\n", substr($text, 4, $end - 4));
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (preg_match('/\A(name|description):[ \t]*(.*)\z/D', $line, $match) !== 1 || isset($fields[$match[1]])) {
                throw new SkillException('Skill frontmatter supports one name and one description.');
            }
            $value = trim($match[2]);
            if ($match[1] === 'description' && in_array($value, ['|', '|-', '>', '>-'], true)) {
                $block = [];
                while (isset($lines[$i + 1]) && preg_match('/\A[ \t]+(.*)\z/D', $lines[$i + 1], $indented) === 1) {
                    $block[] = trim($indented[1]);
                    $i++;
                }
                $fields[$match[1]] = implode(str_starts_with($value, '>') ? ' ' : "\n", $block);
            } else {
                $fields[$match[1]] = self::scalar($value);
            }
        }
        if (! isset($fields['name'], $fields['description'])) {
            throw new SkillException('Skill frontmatter requires name and description.');
        }

        return new Skill($fields['name'], $fields['description'], trim(substr($text, $end + 5)), realpath($directory) ?: null);
    }

    private static function scalar(string $value): string
    {
        if (str_starts_with($value, '"')) {
            try {
                $decoded = json_decode($value, true, 2, JSON_THROW_ON_ERROR);
                if (is_string($decoded)) {
                    return $decoded;
                }
            } catch (Throwable) {
                // Never include frontmatter contents in errors.
            }

            throw new SkillException('Invalid quoted frontmatter scalar.');
        }
        if (str_starts_with($value, "'")) {
            if (strlen($value) < 2 || ! str_ends_with($value, "'")) {
                throw new SkillException('Invalid quoted frontmatter scalar.');
            }

            return str_replace("''", "'", substr($value, 1, -1));
        }
        if ($value === '' || preg_match('/\A[!&*\[\]{}|>]/', $value) === 1) {
            throw new SkillException('Skill metadata must use literal scalar values.');
        }

        return $value;
    }

    /** @param list<Skill> $skills
     * @return list<Skill> */
    private static function validateSkills(array $skills, int $maxFiles, int $maxFileBytes): array
    {
        if (count($skills) > $maxFiles) {
            throw new SkillException('The skill set exceeds its file limit.');
        }
        $seen = [];
        foreach ($skills as $skill) {
            if (! $skill instanceof Skill || isset($seen[$skill->name]) || strlen($skill->body) > $maxFileBytes) {
                throw new SkillException('Skills must have unique names and bounded bodies.');
            }
            $seen[$skill->name] = true;
        }

        return array_values($skills);
    }
}
