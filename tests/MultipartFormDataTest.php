<?php

declare(strict_types=1);

use AiSdk\Utils\Support\MultipartFormData;

it('encodes text and binary multipart fields', function () {
    $multipart = MultipartFormData::encode([
        ['name' => 'model', 'value' => 'scribe_v1'],
        [
            'name' => 'file',
            'value' => "\x00audio\xFF",
            'filename' => 'sample.wav',
            'contentType' => 'audio/wav',
        ],
    ]);

    expect($multipart['boundary'])->toStartWith('aisdk-')
        ->and($multipart['body'])->toContain(
            'Content-Disposition: form-data; name="model"',
            "\r\n\r\nscribe_v1\r\n",
            'Content-Disposition: form-data; name="file"; filename="sample.wav"',
            "Content-Type: audio/wav\r\n",
            "\x00audio\xFF",
        )
        ->and($multipart['body'])->toEndWith("--{$multipart['boundary']}--\r\n");
});

it('expands repeated values and serializes portable scalar and json values', function () {
    $multipart = MultipartFormData::encode([
        ['name' => 'tag[]', 'value' => ['one', 'two']],
        ['name' => 'enabled', 'value' => true],
        ['name' => 'metadata', 'value' => ['language' => 'en']],
    ]);

    expect(substr_count($multipart['body'], 'name="tag[]"'))->toBe(2)
        ->and($multipart['body'])->toContain(
            "\r\n\r\none\r\n",
            "\r\n\r\ntwo\r\n",
            "\r\n\r\ntrue\r\n",
            "\r\n\r\n{\"language\":\"en\"}\r\n",
        );
});

it('prevents multipart part headers from being extended through field metadata', function () {
    $multipart = MultipartFormData::encode([
        [
            'name' => "audio\r\nX-Injected: yes",
            'value' => 'bytes',
            'filename' => "clip\"\r\nX-Filename: yes.wav",
            'contentType' => "audio/wav\r\nX-Content: yes",
        ],
    ]);

    expect($multipart['body'])->not->toContain("\r\nX-Injected:", "\r\nX-Filename:", "\r\nX-Content:")
        ->and($multipart['body'])->toContain(
            'name="audioX-Injected: yes"',
            'filename="clip\\"X-Filename: yes.wav"',
            'Content-Type: audio/wavX-Content: yes',
        );
});
