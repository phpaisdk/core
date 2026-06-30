<?php

declare(strict_types=1);

use AiSdk\Capability;
use AiSdk\Content;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Generate;
use AiSdk\Tests\Fakes\FakeTextModel;

afterEach(fn() => Generate::reset());

it('generates text from a prompt', function () {
    $result = Generate::text('Hello')
        ->model(FakeTextModel::text('Hi there'))
        ->run();

    expect($result->text)->toBe('Hi there')
        ->and($result->usage->inputTokens)->toBe(10)
        ->and($result->usage->totalTokens)->toBe(15);
});

it('uses the configured default model', function () {
    Generate::model(FakeTextModel::text('default reply'));

    expect(Generate::text('Hello')->run()->text)->toBe('default reply');
});

it('requires a prompt or messages', function () {
    Generate::text()
        ->model(FakeTextModel::text('x'))
        ->run();
})->throws(InvalidArgumentException::class);

it('requires a text model', function () {
    Generate::text('Hello')->run();
})->throws(InvalidArgumentException::class);

it('checks typed input capabilities before running the provider', function () {
    Generate::text()
        ->messages([
            \AiSdk\Message::user([
                Content::text('Describe this.'),
                Content::image('raw-image', mimeType: 'image/png'),
            ]),
        ])
        ->model(FakeTextModel::text('nope'))
        ->run();
})->throws(\AiSdk\Exceptions\CapabilityNotSupportedException::class);

it('allows typed input when the model advertises that input capability', function () {
    $result = Generate::text()
        ->messages([
            \AiSdk\Message::user([
                Content::text('Describe this.'),
                Content::image('raw-image', mimeType: 'image/png'),
            ]),
        ])
        ->model(FakeTextModel::textWithCapabilities('ok', [Capability::ImageInput]))
        ->run();

    expect($result->text)->toBe('ok');
});
