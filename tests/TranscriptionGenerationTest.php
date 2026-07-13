<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Generate;
use AiSdk\Tests\Fakes\FakeTranscriptionModel;

afterEach(fn() => Generate::reset());

it('transcribes audio content', function () {
    $model = FakeTranscriptionModel::transcription('A portable transcript.');
    $audio = Content::audio('audio-bytes', mimeType: 'audio/wav', filename: 'sample.wav');

    $result = Generate::transcription($audio)
        ->model($model)
        ->providerOptions('fake', ['language' => 'en'])
        ->run();

    expect($result->output->text)->toBe('A portable transcript.')
        ->and($model->lastRequest?->audio)->toBe($audio)
        ->and($model->lastRequest?->providerOptionsFor('fake'))->toBe(['language' => 'en']);
});

it('uses the configured default transcription model', function () {
    Generate::model(FakeTranscriptionModel::transcription('Default transcript.'));

    expect(Generate::transcription(Content::audio('audio', mimeType: 'audio/wav'))->run()->output->text)
        ->toBe('Default transcript.');
});

it('requires audio content', function () {
    Generate::transcription()
        ->model(FakeTranscriptionModel::transcription())
        ->run();
})->throws(InvalidArgumentException::class, 'requires audio content');

it('rejects non-audio content', function () {
    Generate::transcription(Content::file('file-bytes', mimeType: 'application/pdf'));
})->throws(InvalidArgumentException::class, 'requires audio content');

it('requires a transcription model', function () {
    Generate::transcription(Content::audio('audio', mimeType: 'audio/wav'))->run();
})->throws(InvalidArgumentException::class);
