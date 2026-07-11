<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Generate;
use AiSdk\Tests\Fakes\FakeEmbeddingModel;
use AiSdk\Tests\Fakes\FakeImageModel;
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

it('keeps defaults for different modalities independently', function () {
    Generate::model(FakeTextModel::text('default text'));
    Generate::model(FakeImageModel::image('aW1hZ2U='));
    Generate::model(FakeEmbeddingModel::embedding([0.4, 0.5]));

    expect(Generate::text('Hello')->run()->text)->toBe('default text')
        ->and(Generate::image('Draw')->run()->output->base64)->toBe('aW1hZ2U=')
        ->and(Generate::embedding('Embed')->run()->output->vector)->toBe([0.4, 0.5]);
});

it('requires a prompt or messages', function () {
    Generate::text()
        ->model(FakeTextModel::text('x'))
        ->run();
})->throws(InvalidArgumentException::class);

it('requires a text model', function () {
    Generate::text('Hello')->run();
})->throws(InvalidArgumentException::class);

it('forwards typed input to the selected provider adapter', function () {
    $result = Generate::text()
        ->messages([
            \AiSdk\Message::user([
                Content::text('Describe this.'),
                Content::image('raw-image', mimeType: 'image/png'),
            ]),
        ])
        ->model(FakeTextModel::text('ok'))
        ->run();

    expect($result->text)->toBe('ok');
});
