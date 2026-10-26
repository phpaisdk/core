<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\ContentSource;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\InputEncoding;
use AiSdk\LocalFile;

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

it('resolves a readable local media file as raw input', function () {
    $path = tempnam(sys_get_temp_dir(), 'aisdk-content-');
    file_put_contents($path, 'local-bytes');

    try {
        $content = Content::audio(LocalFile::at($path), mimeType: 'audio/wav');

        expect($content->source())->toBe(ContentSource::Raw)
            ->and($content->data())->toBe('local-bytes')
            ->and($content->mimeType())->toBe('audio/wav')
            ->and($content->filename())->toBe(basename($path));
    } finally {
        @unlink($path);
    }
});

it('requires a mime type for explicit base64 input', function () {
    Content::file('JVBERi0=', encoding: InputEncoding::Base64);
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class, 'Base64 content requires a MIME type.');

it('rejects malformed data uri input', function () {
    Content::audio('data:not-valid');
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class, 'Invalid data URI content.');

it('requires a mime type for raw input', function () {
    Content::image('not-a-path-or-url');
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('resolves video urls', function () {
    $content = Content::video('https://example.com/clips/demo.mp4');

    expect($content->type)->toBe(Content::TYPE_VIDEO)
        ->and($content->source())->toBe(ContentSource::Url)
        ->and($content->url())->toBe('https://example.com/clips/demo.mp4')
        ->and($content->filename())->toBe('demo.mp4');
});

it('resolves video data uri input', function () {
    $content = Content::video('data:video/mp4;base64,AAAA');

    expect($content->type)->toBe(Content::TYPE_VIDEO)
        ->and($content->source())->toBe(ContentSource::DataUri)
        ->and($content->mimeType())->toBe('video/mp4')
        ->and($content->base64Data())->toBe('AAAA');
});

it('resolves explicit base64 video input', function () {
    $content = Content::video('AAAA', mimeType: 'video/mp4', filename: 'clip.mp4', encoding: InputEncoding::Base64);

    expect($content->source())->toBe(ContentSource::Base64)
        ->and($content->data())->toBe('AAAA')
        ->and($content->mimeType())->toBe('video/mp4')
        ->and($content->filename())->toBe('clip.mp4');
});

it('resolves local video file input', function () {
    $path = tempnam(sys_get_temp_dir(), 'aisdk-video-content-');
    file_put_contents($path, 'video-file-bytes');

    try {
        $content = Content::video(LocalFile::at($path), mimeType: 'video/mp4');

        expect($content->source())->toBe(ContentSource::Raw)
            ->and($content->data())->toBe('video-file-bytes')
            ->and($content->mimeType())->toBe('video/mp4')
            ->and($content->filename())->toBe(basename($path));
    } finally {
        @unlink($path);
    }
});

it('resolves raw video bytes', function () {
    $content = Content::video("\x00\x00video-bytes", mimeType: 'video/webm', filename: 'clip.webm');

    expect($content->source())->toBe(ContentSource::Raw)
        ->and($content->data())->toBe("\x00\x00video-bytes")
        ->and($content->base64Data())->toBe(base64_encode("\x00\x00video-bytes"))
        ->and($content->mimeType())->toBe('video/webm');
});

it('requires a mime type for raw video input', function () {
    Content::video('raw-video-bytes');
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class, 'Raw content requires a MIME type.');

it('requires a mime type for base64 video input', function () {
    Content::video('AAAA', encoding: InputEncoding::Base64);
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class, 'Base64 content requires a MIME type.');

it('rejects malformed video data uri input', function () {
    Content::video('data:video/mp4;broken');
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class, 'Invalid data URI content.');

it('never reads the disk from a plain string', function () {
    $path = tempnam(sys_get_temp_dir(), 'aisdk-secret-');
    file_put_contents($path, 'top-secret');

    try {
        $content = Content::file($path, mimeType: 'text/plain');

        expect($content->data())->toBe($path)->and($content->data())->not->toContain('top-secret');
        expect(fn() => Content::file($path))->toThrow(InvalidArgumentException::class);
    } finally {
        @unlink($path);
    }
});

it('confines LocalFile::within() to its base directory', function () {
    $base = sys_get_temp_dir() . '/aisdk-base-' . bin2hex(random_bytes(4));
    mkdir($base);
    file_put_contents($base . '/ok.txt', 'ok');
    $outside = tempnam(sys_get_temp_dir(), 'aisdk-outside-');
    file_put_contents($outside, 'outside');
    symlink($outside, $base . '/link.txt');

    try {
        expect(Content::file(LocalFile::within($base, 'ok.txt'), mimeType: 'text/plain')->data())->toBe('ok');

        foreach (['../' . basename($outside), $outside, 'link.txt', 'missing.txt', "ok.txt\0"] as $attempt) {
            expect(fn() => LocalFile::within($base, $attempt))->toThrow(InvalidArgumentException::class);
        }
    } finally {
        @unlink($base . '/link.txt');
        @unlink($base . '/ok.txt');
        @rmdir($base);
        @unlink($outside);
    }
});
