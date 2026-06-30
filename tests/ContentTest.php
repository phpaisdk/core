<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\ContentSource;
use AiSdk\InputEncoding;

it('creates explicit text content', function () {
    $content = Content::text('Hello');

    expect($content->type)->toBe(Content::TYPE_TEXT)
        ->and($content->source())->toBe(ContentSource::Text)
        ->and($content->textValue())->toBe('Hello');
});

it('resolves remote media input as a url source', function () {
    $content = Content::image('https://example.com/photo.png');

    expect($content->type)->toBe(Content::TYPE_IMAGE)
        ->and($content->source())->toBe(ContentSource::Url)
        ->and($content->url())->toBe('https://example.com/photo.png');
});

it('resolves data uri input', function () {
    $content = Content::audio('data:audio/wav;base64,UklGRg==');

    expect($content->source())->toBe(ContentSource::DataUri)
        ->and($content->mimeType())->toBe('audio/wav')
        ->and($content->base64Data())->toBe('UklGRg==')
        ->and($content->dataUri())->toBe('data:audio/wav;base64,UklGRg==');
});

it('base64 encodes non-base64 data uri input', function () {
    $content = Content::file('data:text/plain,hello%20sdk');

    expect($content->source())->toBe(ContentSource::DataUri)
        ->and($content->mimeType())->toBe('text/plain')
        ->and($content->base64Data())->toBe(base64_encode('hello sdk'));
});

it('resolves explicit base64 input', function () {
    $content = Content::file('JVBERi0=', mimeType: 'application/pdf', filename: 'report.pdf', encoding: InputEncoding::Base64);

    expect($content->source())->toBe(ContentSource::Base64)
        ->and($content->base64Data())->toBe('JVBERi0=')
        ->and($content->filename())->toBe('report.pdf');
});

it('resolves raw input', function () {
    $content = Content::image('raw-bytes', mimeType: 'image/png');

    expect($content->source())->toBe(ContentSource::Raw)
        ->and($content->base64Data())->toBe(base64_encode('raw-bytes'));
});

it('requires a mime type for raw input', function () {
    Content::image('not-a-path-or-url');
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);
