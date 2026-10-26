<?php

declare(strict_types=1);

use AiSdk\Utils\Errors\Redactor;

it('masks credential query parameters and userinfo in URLs', function (string $input, string $expected) {
    expect(Redactor::url($input))->toBe($expected);
})->with([
    ['https://api.test/v1?key=abc123&model=x', 'https://api.test/v1?key=[redacted]&model=x'],
    ['wss://live.test/ws?api_key=abc&access_token=def#frag', 'wss://live.test/ws?api_key=[redacted]&access_token=[redacted]#frag'],
    ['https://user:pass@host.test/path', 'https://[redacted]@host.test/path'],
    ['https://host.test/?X-Amz-Signature=deadbeef&Expires=1', 'https://host.test/?X-Amz-Signature=[redacted]&Expires=1'],
    ['https://host.test/path?model=gpt&stream=true', 'https://host.test/path?model=gpt&stream=true'],
]);

it('redacts URLs, bearer tokens and API key shapes in free text', function () {
    $text = 'cURL error for wss://g.test/ws?key=SECRET1234 with Authorization: Bearer abcdef123456789 and sk-proj-ABCDEFGHIJKLMNOPQRSTUV';
    $redacted = Redactor::text($text);

    expect($redacted)->not->toContain('SECRET1234')
        ->and($redacted)->not->toContain('abcdef123456789')
        ->and($redacted)->not->toContain('ABCDEFGHIJKLMNOPQRSTUV')
        ->and($redacted)->toContain('cURL error for');
});
