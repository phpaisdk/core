<?php

declare(strict_types=1);

use AiSdk\Generate;
use AiSdk\Tests\Fakes\FakeImageModel;

afterEach(fn() => Generate::reset());

it('generates images from a prompt', function () {
    $model = FakeImageModel::image();

    $result = Generate::image('A red cube')
        ->model($model)
        ->count(2)
        ->size('1024x1024')
        ->aspectRatio('1:1')
        ->seed(123)
        ->providerOptions('fake', ['style' => 'vivid'])
        ->run();

    expect($result->output->base64)->toBe('aW1hZ2U=')
        ->and($result->images)->toHaveCount(1)
        ->and($model->lastRequest?->prompt)->toBe('A red cube')
        ->and($model->lastRequest?->count)->toBe(2)
        ->and($model->lastRequest?->size)->toBe('1024x1024')
        ->and($model->lastRequest?->aspectRatio)->toBe('1:1')
        ->and($model->lastRequest?->seed)->toBe(123)
        ->and($model->lastRequest?->providerOptionsFor('fake'))->toBe(['style' => 'vivid']);
});

it('uses the configured default image model', function () {
    Generate::model(FakeImageModel::image('ZGVmYXVsdA=='));

    expect(Generate::image('A default image')->run()->output->base64)->toBe('ZGVmYXVsdA==');
});

it('requires an image prompt', function () {
    Generate::image('   ')
        ->model(FakeImageModel::image())
        ->run();
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('requires an image model', function () {
    Generate::image('A red cube')->run();
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('requires image generation capability', function () {
    Generate::image('A red cube')
        ->model(FakeImageModel::withoutImageGeneration())
        ->run();
})->throws(\AiSdk\Exceptions\CapabilityNotSupportedException::class);

it('decodes and saves image data', function () {
    $image = Generate::image('A red cube')
        ->model(FakeImageModel::image(base64_encode('png-bytes')))
        ->run()
        ->output;

    $path = sys_get_temp_dir() . '/aisdk-image-test-' . bin2hex(random_bytes(4)) . '.png';

    expect($image->bytes())->toBe('png-bytes')
        ->and($image->save($path))->toBe($path)
        ->and(file_get_contents($path))->toBe('png-bytes');

    @unlink($path);
});
