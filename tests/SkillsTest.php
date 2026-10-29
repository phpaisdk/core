<?php

declare(strict_types=1);

use AiSdk\Agent;
use AiSdk\Exceptions\SkillException;
use AiSdk\Skills\LoadSkill;
use AiSdk\Skills\Skill;
use AiSdk\Skills\SkillLoader;
use AiSdk\Testing\Fakes\FakeTextModel;

function createSkill(string $root, string $name = 'research', string $body = 'Read the reference carefully.'): string
{
    $directory = $root . '/' . $name;
    mkdir($directory);
    file_put_contents($directory . '/SKILL.md', "---\nname: $name\ndescription: Research relevant facts\n---\n$body\n");

    return $directory;
}

function removeSkillFixture(string $path): void
{
    if (is_link($path) || ! is_dir($path)) {
        unlink($path);

        return;
    }
    foreach (new DirectoryIterator($path) as $entry) {
        if (! $entry->isDot()) {
            removeSkillFixture($entry->getPathname());
        }
    }
    rmdir($path);
}

beforeEach(function () {
    $this->skillRoot = sys_get_temp_dir() . '/phpaisdk-skills-' . bin2hex(random_bytes(8));
    mkdir($this->skillRoot);
});

afterEach(function () {
    removeSkillFixture($this->skillRoot);
});

it('loads skill metadata and text from explicit roots without executing scripts', function () {
    $directory = createSkill($this->skillRoot, body: '<?php throw new RuntimeException("must never execute"); ?>');
    mkdir($directory . '/resources');
    file_put_contents($directory . '/resources/facts.md', 'Known facts');
    $loader = new SkillLoader([$this->skillRoot]);
    $skills = $loader->load();
    expect($skills)->toHaveCount(1)->and($skills[0]->name)->toBe('research')
        ->and($loader->read('research'))->toContain('<?php')
        ->and($loader->read('research', 'resources/facts.md'))->toBe('Known facts');
    $tool = new LoadSkill($loader);
    expect($tool->call(['name' => 'research', 'resource' => 'resources/facts.md']))->toBe('Known facts');
});

it('accepts direct skill roots and quoted or folded descriptions', function (string $description, string $expected) {
    $directory = createSkill($this->skillRoot);
    file_put_contents($directory . '/SKILL.md', "---\nname: 'research'\ndescription: $description\n---\nBody\n");
    $skill = (new SkillLoader([$directory]))->load()[0];
    expect($skill->description)->toBe($expected)->and($skill->body)->toBe('Body');
})->with([
    ['"Find facts"', 'Find facts'],
    ["'Find facts'", 'Find facts'],
    [">-\n  Find relevant\n  facts", 'Find relevant facts'],
]);

it('adds a skill index and load tool to an immutable agent clone', function () {
    $model = FakeTextModel::text('ok');
    $base = new Agent($model, instructions: 'Base instructions');
    $agent = $base->withSkills([new Skill('research', 'Find facts', 'The detailed instructions')]);
    $agent->run('hi');
    expect($model->lastRequest->system)->toContain('Base instructions', 'research: Find facts')
        ->not->toContain('The detailed instructions')->and($model->lastRequest->tools[0]->name())->toBe('load_skill')
        ->and($model->lastRequest->tools[0]->call(['name' => 'research']))->toBe('The detailed instructions');
    $base->run('hi');
    expect($model->lastRequest->system)->toBe('Base instructions')->and($model->lastRequest->tools)->toBe([]);
});

it('rejects resource traversal and absolute or stream-wrapper paths', function (string $resource) {
    createSkill($this->skillRoot);
    $loader = new SkillLoader([$this->skillRoot]);
    expect(fn() => $loader->read('research', $resource))->toThrow(SkillException::class);
})->with(['../private', 'resources/../../private', '/etc/passwd', 'php://filter/resource=SKILL.md', 'C:\\secret', './SKILL.md', "bad\0path", 'a//b']);

it('rejects symlink escapes for skill directories, manifests and resources', function (string $kind) {
    $directory = createSkill($this->skillRoot);
    $outside = $this->skillRoot . '/outside.txt';
    file_put_contents($outside, 'Must not be returned');
    $loader = new SkillLoader([$directory]);
    if ($kind === 'directory') {
        symlink($directory, $this->skillRoot . '/linked-skill');
        expect(fn() => (new SkillLoader([$this->skillRoot]))->load())->toThrow(SkillException::class);
    } elseif ($kind === 'manifest') {
        unlink($directory . '/SKILL.md');
        symlink($outside, $directory . '/SKILL.md');
        expect(fn() => $loader->load())->toThrow(SkillException::class);
    } else {
        symlink($outside, $directory . '/secret.md');
        expect(fn() => $loader->read('research', 'secret.md'))->toThrow(SkillException::class);
    }
})->with(['directory', 'manifest', 'resource']);

it('bounds manifest size, resource size, file counts and duplicate names', function () {
    $directory = createSkill($this->skillRoot, body: str_repeat('a', 200));
    expect(fn() => (new SkillLoader([$directory], maxFileBytes: 100))->load())->toThrow(SkillException::class);
    file_put_contents($directory . '/SKILL.md', "---\nname: research\ndescription: Facts\n---\nBody\n");
    file_put_contents($directory . '/large.txt', str_repeat('x', 101));
    expect(fn() => (new SkillLoader([$directory], maxFileBytes: 100))->read('research', 'large.txt'))->toThrow(SkillException::class);
    createSkill($this->skillRoot, 'second');
    expect(fn() => (new SkillLoader([$this->skillRoot], maxFiles: 1))->load())->toThrow(SkillException::class);
    expect(fn() => SkillLoader::fromSkills([new Skill('a', 'One', 'one'), new Skill('a', 'Two', 'two')]))->toThrow(SkillException::class);
});

it('rejects malformed or executable frontmatter syntax without exposing its contents', function (string $frontmatter) {
    $directory = createSkill($this->skillRoot);
    file_put_contents($directory . '/SKILL.md', $frontmatter);

    try {
        (new SkillLoader([$directory]))->load();
        test()->fail('Invalid frontmatter was accepted');
    } catch (SkillException $error) {
        expect($error->getMessage())->not->toContain('private-value');
    }
})->with([
    "name: research\ndescription: private-value",
    "---\nname: research\n---\nBody\n",
    "---\nname: research\nname: research\ndescription: private-value\n---\nBody\n",
    "---\nname: ../private-value\ndescription: Facts\n---\nBody\n",
    "---\nname: research\ndescription: !php/object private-value\n---\nBody\n",
    "---\nname: research\ndescription: [private-value]\n---\nBody\n",
    "---\nname: research\ndescription: \"private-value\n---\nBody\n",
]);

it('freezes the advertised manifest set and does not give inline skills filesystem access', function () {
    $directory = createSkill($this->skillRoot);
    $loader = (new SkillLoader([$this->skillRoot]))->snapshot();
    file_put_contents($directory . '/SKILL.md', "changed");
    expect($loader->read('research'))->toBe('Read the reference carefully.');
    expect(fn() => SkillLoader::fromSkills([new Skill('inline', 'Description', 'Body')])->read('inline', 'file.txt'))->toThrow(SkillException::class);
    expect(fn() => $loader->read('unknown'))->toThrow(SkillException::class);
});
