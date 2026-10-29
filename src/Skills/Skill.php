<?php

declare(strict_types=1);

namespace AiSdk\Skills;

use AiSdk\Exceptions\SkillException;

final readonly class Skill
{
    public function __construct(
        public string $name,
        public string $description,
        public string $body,
        public ?string $directory = null,
    ) {
        if (strlen($name) > 64 || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $name) !== 1) {
            throw new SkillException('Skill names must contain lowercase letters, digits and single hyphens, up to 64 bytes.');
        }
        if (trim($description) === '' || strlen($description) > 1024 || strlen($body) > 16 * 1024 * 1024 || preg_match('//u', $description . $body) !== 1) {
            throw new SkillException('Skills require a UTF-8 description up to 1024 bytes and a bounded UTF-8 body.');
        }
    }
}
