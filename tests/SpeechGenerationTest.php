<?php

declare(strict_types=1);

use AiSdk\Generate;
use AiSdk\Tests\Fakes\FakeSpeechModel;

afterEach(fn() => Generate::reset());

it('generates speech from input text', function () {
    $model = FakeSpeechModel::speech();

    $result = Generate::speech('Read this aloud')
        ->model($model)
        ->voice('coral')
        ->format('mp3')
        ->providerOptions('fake', ['style' => 'warm'])
        ->run();

    expect($result->output->data)->toBe('audio-bytes')
        ->and($result->output->mimeType)->toBe('audio/mpeg')
        ->and($model->lastRequest?->input)->toBe('Read this aloud')
        ->and($model->lastRequest?->voice)->toBe('coral')
        ->and($model->lastRequest?->format)->toBe('mp3')
        ->and($model->lastRequest?->providerOptionsFor('fake'))->toBe(['style' => 'warm']);
});

it('uses the configured default speech model', function () {
    Generate::model(FakeSpeechModel::speech('default-audio'));

    expect(Generate::speech('A default speech request')->run()->output->data)->toBe('default-audio');
});

it('requires speech input', function () {
    Generate::speech('   ')
        ->model(FakeSpeechModel::speech())
        ->run();
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('requires a speech model', function () {
    Generate::speech('Read this aloud')->run();
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('encodes and saves audio data', function () {
    $audio = Generate::speech('Read this aloud')
        ->model(FakeSpeechModel::speech('audio-bytes', 'audio/wav'))
        ->run()
        ->output;

    $path = sys_get_temp_dir() . '/aisdk-audio-test-' . bin2hex(random_bytes(4)) . '.wav';

    expect($audio->base64())->toBe(base64_encode('audio-bytes'))
        ->and($audio->dataUri())->toBe('data:audio/wav;base64,' . base64_encode('audio-bytes'))
        ->and($audio->save($path))->toBe($path)
        ->and(file_get_contents($path))->toBe('audio-bytes');

    @unlink($path);
});
