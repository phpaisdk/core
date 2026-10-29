<?php

declare(strict_types=1);

namespace AiSdk\Skills;

use AiSdk\Schema;
use AiSdk\Tool;

/** Reads text only. No scripts, shell commands or PHP files are ever executed. */
final class LoadSkill extends Tool
{
    /** @param SkillLoader|list<Skill> $skills */
    public function __construct(SkillLoader|array $skills)
    {
        $loader = ($skills instanceof SkillLoader ? $skills : SkillLoader::fromSkills($skills))->snapshot();
        parent::__construct('load_skill', 'Load an available skill body, or a named text resource inside its directory.', Schema::object('input', properties: [
            Schema::string('name', 'The advertised skill name')->required(),
            Schema::string('resource', 'Optional relative resource filename'),
        ]), static fn(array $input): string => $loader->read($input['name'], $input['resource'] ?? null));
    }
}
