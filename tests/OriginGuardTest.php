<?php

declare(strict_types=1);

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Utils\Http\OriginGuard;

it('compares scheme host and effective port while ignoring other URL components', function (string $url, string $baseUrl) {
    OriginGuard::assertSameOrigin($url, $baseUrl);
    expect(true)->toBeTrue();
})->with([
    ['https://api.example.com/result?token=signed#fragment', 'https://api.example.com/v1'],
    ['HTTPS://API.EXAMPLE.COM:443/result', 'https://api.example.com/v1'],
    ['https://api.example.com/result', 'HTTPS://API.EXAMPLE.COM:443/v1'],
    ['http://api.example.com:80/result', 'http://api.example.com/v1'],
    ['http://api.example.com/result', 'HTTP://API.EXAMPLE.COM:80/v1'],
    ['https://custom.example:8443/result', 'https://custom.example:8443/v1'],
    ['http://custom.example:443/result', 'http://custom.example:443/v1'],
    ['https://[2001:DB8::1]:443/result', 'https://[2001:db8::1]/v1'],
    ['http://[::1]:8080/result', 'http://[::1]:8080/v1'],
    ['https://api.example.com./result', 'https://api.example.com./v1'],
    // The original guards impose no arbitrary path-length limit.
    ['https://api.example.com/' . str_repeat('x', 100_000), 'https://api.example.com/v1'],
]);

it('rejects untrusted or malformed URLs without disclosing them', function (string $url) {
    try {
        OriginGuard::assertSameOrigin($url, 'https://api.example.com/v1');
        test()->fail('Expected origin rejection.');
    } catch (InvalidResponseException $error) {
        expect($error->getMessage())->toBe('Provider follow-up URL has an untrusted origin.');
        expect((string) $error)->not->toContain('signed-secret');
    }
})->with([
    'https://evil.test/result?token=signed-secret',
    'http://api.example.com/result',
    'https://api.example.com:444/result',
    'https://api.example.com@evil.test/result',
    'https://user:password@api.example.com/result',
    'https://user@api.example.com/result',
    'https://@api.example.com/result',
    'https://:password@api.example.com/result',
    'https://api.example.com.evil.test/result',
    'https://api.example.com\\@evil.test/result',
    'https://api.example.com/path\\result',
    '/relative/result',
    '//api.example.com/result',
    '',
    'https://',
    'https:///result',
    'https://api.example.com:99999/result',
    'https://api.example.com:' . str_repeat('9', 100_000) . '/result',
    'https://api.example.com:0/result',
    'https://api.example.com:-1/result',
    'https://api.example.com:port/result',
    "https://api.example.com/result\n",
    'https://api.example.com/result with spaces',
    'file:///result',
    'ftp://api.example.com/result',
    'https://api.example.com./result',
    'https://[2001:db8::1]/result',
]);

it('rejects every ASCII control character and space anywhere in either URL', function (int $code) {
    $url = 'https://api.example.com/result' . chr($code);
    expect(fn() => OriginGuard::assertSameOrigin($url, 'https://api.example.com'))
        ->toThrow(InvalidResponseException::class);
    expect(fn() => OriginGuard::assertSameOrigin('https://api.example.com/result', $url))
        ->toThrow(InvalidResponseException::class);
})->with([...range(0, 32), 127]);

it('rejects invalid base configurations even when matching or allowing a sibling', function (string $baseUrl) {
    expect(fn() => OriginGuard::assertSameOrigin($baseUrl, $baseUrl))
        ->toThrow(InvalidResponseException::class);
    expect(fn() => OriginGuard::assertSameOrigin('https://queue.example/result', $baseUrl, ['https://queue.example'], [$baseUrl]))
        ->toThrow(InvalidResponseException::class);
})->with(['', '/relative', 'https://', 'https://user:password@api.example.com', 'https://api.example.com:0', "https://api.example.com/\n", 'https://api.example.com/\\']);

it('retains explicit non-default ports and IPv6 host spelling', function (string $url, string $baseUrl) {
    expect(fn() => OriginGuard::assertSameOrigin($url, $baseUrl))->toThrow(InvalidResponseException::class);
})->with([
    ['https://custom.example/result', 'https://custom.example:8443/v1'],
    ['https://custom.example:8444/result', 'https://custom.example:8443/v1'],
    ['https://[::1]/result', 'https://[0:0:0:0:0:0:0:1]/v1'],
    ['https://[::1]:0/result', 'https://[::1]/v1'],
    ['https://[::2]/result', 'https://[::1]/v1'],
]);

it('allows only explicit siblings when the configured base is official', function (string $baseUrl, string $sibling, array $origins) {
    OriginGuard::assertSameOrigin($sibling . '/result', strtoupper($baseUrl) . ':443/v1', $origins, [$baseUrl]);
    foreach (['http://' . substr($sibling, 8), $sibling . ':444', $sibling . '.evil.test', $sibling . '.'] as $untrusted) {
        expect(fn() => OriginGuard::assertSameOrigin($untrusted . '/result', $baseUrl, $origins, [$baseUrl]))
            ->toThrow(InvalidResponseException::class);
    }
    foreach (['https://custom.example', $baseUrl . ':8443', 'http://' . substr($baseUrl, 8)] as $custom) {
        expect(fn() => OriginGuard::assertSameOrigin($sibling . '/result', $custom, $origins, [$baseUrl]))
            ->toThrow(InvalidResponseException::class);
    }
})->with([
    ['https://fal.run', 'https://queue.fal.run', ['https://queue.fal.run']],
    ['https://api.bfl.ai', 'https://api.us1.bfl.ai', ['https://api.bfl.ai', 'https://api.us1.bfl.ai', 'https://api.eu1.bfl.ai']],
    ['https://api.bfl.ai', 'https://api.eu1.bfl.ai', ['https://api.bfl.ai', 'https://api.us1.bfl.ai', 'https://api.eu1.bfl.ai']],
    ['https://api.us1.bfl.ai', 'https://api.eu1.bfl.ai', ['https://api.bfl.ai', 'https://api.us1.bfl.ai', 'https://api.eu1.bfl.ai']],
    ['https://api.eu1.bfl.ai', 'https://api.bfl.ai', ['https://api.bfl.ai', 'https://api.us1.bfl.ai', 'https://api.eu1.bfl.ai']],
]);

it('requires explicit official configurations and ignores malformed allow-list entries', function () {
    expect(fn() => OriginGuard::assertSameOrigin('https://queue.example/result', 'https://api.example.com', ['https://queue.example']))
        ->toThrow(InvalidResponseException::class);
    expect(fn() => OriginGuard::assertSameOrigin('https://queue.example/result', 'https://api.example.com', ['https://user@queue.example'], ['https://api.example.com']))
        ->toThrow(InvalidResponseException::class);
    expect(fn() => OriginGuard::assertSameOrigin('https://queue.example/result', 'https://api.example.com', ['https://queue.example'], ['https://user@api.example.com']))
        ->toThrow(InvalidResponseException::class);
    OriginGuard::assertSameOrigin('https://api.example.com/result', 'https://api.example.com', ['invalid'], ['invalid']);
});
