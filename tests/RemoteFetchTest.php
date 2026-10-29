<?php

declare(strict_types=1);

use AiSdk\Exceptions\APIConnectionException;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\NotFoundException;
use AiSdk\Exceptions\RemoteFetchException;
use AiSdk\Support\Sdk;
use AiSdk\Utils\Http\RemoteFetch;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function remoteClient(array $responses): ClientInterface
{
    return new class ($responses) implements ClientInterface {
        public array $requests = [];

        public function __construct(private array $responses) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->requests[] = $request;
            $response = array_shift($this->responses);
            if ($response instanceof Throwable) {
                throw $response;
            }
            if ($response instanceof Closure) {
                return $response($request);
            }

            return $response ?? throw new RuntimeException('Unexpected request');
        }
    };
}

function publicRemoteDns(string $host): array
{
    return ['93.184.216.34', '2606:4700:4700::1111'];
}

it('returns bounded bytes MIME type and final URL through the supplied SDK', function () {
    $client = remoteClient([new Response(200, ['Content-Type' => 'audio/mpeg; charset=binary', 'Content-Length' => '0005'], 'audio')]);
    $factory = new HttpFactory();
    $result = RemoteFetch::bytes(new Sdk($client, $factory, $factory), 'https://media.example/audio?token=signed#fragment', 5, dnsResolver: publicRemoteDns(...));
    expect($result->body)->toBe('audio')->and($result->mimeType)->toBe('audio/mpeg')
        ->and($result->finalUrl)->toBe('https://media.example/audio?token=signed')
        ->and($client->requests[0]->getHeaderLine('Accept-Encoding'))->toBe('identity');
});

it('follows up to three relative redirects and resolves every destination again', function () {
    $client = remoteClient([
        new Response(302, ['Location' => '/one']),
        new Response(307, ['Location' => '//other.example/two']),
        new Response(308, ['Location' => '../three']),
        new Response(200, [], 'ok'),
    ]);
    $hosts = [];
    $result = RemoteFetch::bytes($client, 'https://media.example/start', dnsResolver: function (string $host) use (&$hosts): array {
        $hosts[] = $host;

        return publicRemoteDns($host);
    });
    expect($result->body)->toBe('ok')->and($result->mimeType)->toBeNull()
        ->and($result->finalUrl)->toBe('https://other.example/three')
        ->and($hosts)->toBe(['media.example', 'media.example', 'other.example', 'other.example'])
        ->and($client->requests)->toHaveCount(4);
});

it('rejects invalid URLs before sending requests', function (string $url) {
    $client = remoteClient([]);
    expect(fn() => RemoteFetch::bytes($client, $url, dnsResolver: publicRemoteDns(...)))->toThrow(InvalidArgumentException::class);
    expect($client->requests)->toBe([]);
})->with([
    'file:///etc/passwd', 'ftp://media.example/a', 'data:text/plain,hello', '/relative',
    'https://user:pass@media.example/a', 'https://@media.example', 'https://media.example:0',
    "https://media.example/\r\nsecret", 'https://media.example\\@127.0.0.1',
    'http://2130706433', 'http://127.1', 'http://0x7f000001', 'http://0177.0.0.1',
    'https://%31%32%37.0.0.1', 'http://[invalid]', 'https://' . str_repeat('a', 9000),
]);

it('blocks non-public IPv4 and IPv6 destinations before sending requests', function (string $address) {
    $client = remoteClient([]);
    $url = str_contains($address, ':') ? 'http://[' . $address . ']/file' : 'http://' . $address . '/file';
    expect(fn() => RemoteFetch::bytes($client, $url))->toThrow(RemoteFetchException::class);
    expect($client->requests)->toBe([]);
})->with([
    '127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.0.1', '169.254.169.254', '169.254.170.2',
    '100.100.100.200', '100.64.0.1', '0.0.0.0', '224.0.0.1', '255.255.255.255',
    '198.18.0.1', '192.0.2.1', '192.0.0.1', '168.63.129.16',
    '::', '::1', 'fc00::1', 'fd00:ec2::254', 'fe80::1', 'ff02::1',
    '::ffff:127.0.0.1', '::ffff:169.254.169.254', '64:ff9b::a00:1', '2002:7f00:1::', '2001:db8::1',
]);

it('blocks a DNS name when any resolved address is private', function () {
    $client = remoteClient([]);
    expect(fn() => RemoteFetch::bytes($client, 'https://mixed.example', dnsResolver: fn() => ['93.184.216.34', '::1']))->toThrow(RemoteFetchException::class);
    expect($client->requests)->toBe([]);
});

it('permits private destinations only with explicit opt-in', function () {
    $client = remoteClient([new Response(200, [], 'local'), new Response(200, [], 'resolved local')]);
    expect(RemoteFetch::bytes($client, 'http://127.0.0.1', allowPrivateNetworks: true)->body)->toBe('local')
        ->and(RemoteFetch::bytes($client, 'http://local.example', allowPrivateNetworks: true, dnsResolver: fn() => ['::1'])->body)->toBe('resolved local');
});

it('revalidates redirect schemes userinfo and private destinations', function (string $location, string $exception) {
    $client = remoteClient([new Response(302, ['Location' => $location])]);
    expect(fn() => RemoteFetch::bytes($client, 'https://media.example', dnsResolver: fn($host) => $host === 'internal.example' ? ['10.0.0.1'] : publicRemoteDns($host)))->toThrow($exception);
    expect($client->requests)->toHaveCount(1);
})->with([
    ['http://169.254.169.254/credentials', RemoteFetchException::class],
    ['https://internal.example', RemoteFetchException::class],
    ['file:///secret', InvalidArgumentException::class],
    ['https://user:password@media.example', InvalidArgumentException::class],
    ['https://@media.example', InvalidArgumentException::class],
    ['', InvalidArgumentException::class],
]);

it('limits redirect loops to three hops', function () {
    $client = remoteClient(array_fill(0, 4, new Response(302, ['Location' => '/loop'])));

    try {
        RemoteFetch::bytes($client, 'https://media.example', dnsResolver: publicRemoteDns(...));
        test()->fail('Expected redirect limit');
    } catch (RemoteFetchException $error) {
        expect($error->context()['reason'])->toBe('too_many_redirects');
    }
    expect($client->requests)->toHaveCount(4);
});

it('rejects oversized Content-Length before reading and closes the body', function (string $length) {
    $reads = 0;
    $closed = false;
    $stream = FnStream::decorate(Utils::streamFor('large'), [
        'read' => function () use (&$reads): string {
            $reads++;

            return 'never';
        },
        'close' => function () use (&$closed): void {
            $closed = true;
        },
    ]);
    $client = remoteClient([new Response(200, ['Content-Length' => $length], $stream)]);
    expect(fn() => RemoteFetch::bytes($client, 'https://media.example', 4, dnsResolver: publicRemoteDns(...)))->toThrow(RemoteFetchException::class);
    expect($reads)->toBe(0)->and($closed)->toBeTrue();
})->with(['5', '99999999999999999999999999999999']);

it('stops reading immediately after the cap even without a trustworthy length', function (?string $length) {
    $source = Utils::streamFor(str_repeat('x', 100000));
    $bytesRead = 0;
    $closed = false;
    $stream = FnStream::decorate($source, [
        'read' => function (int $length) use ($source, &$bytesRead): string {
            $chunk = $source->read($length);
            $bytesRead += strlen($chunk);

            return $chunk;
        },
        'close' => function () use (&$closed): void {
            $closed = true;
        },
    ]);
    $client = remoteClient([new Response(200, $length === null ? [] : ['Content-Length' => $length], $stream)]);

    try {
        RemoteFetch::bytes($client, 'https://media.example', 10000, dnsResolver: publicRemoteDns(...));
        test()->fail('Expected size limit');
    } catch (RemoteFetchException $error) {
        expect($error->context()['reason'])->toBe('response_too_large');
    }
    expect($bytesRead)->toBe(10001)->and($closed)->toBeTrue();
})->with([null, '1']);

it('rejects invalid limits and malformed length headers', function () {
    foreach ([0, -1, PHP_INT_MAX] as $limit) {
        expect(fn() => RemoteFetch::bytes(remoteClient([]), 'https://media.example', $limit))->toThrow(InvalidArgumentException::class);
    }
    expect(fn() => RemoteFetch::bytes(remoteClient([new Response(200, ['Content-Length' => '-10'])]), 'https://media.example', dnsResolver: publicRemoteDns(...)))->toThrow(RemoteFetchException::class);
});

it('normalizes failed resolution without making network requests', function (array $addresses) {
    $client = remoteClient([]);
    expect(fn() => RemoteFetch::bytes($client, 'https://media.example', dnsResolver: fn() => $addresses))->toThrow(APIConnectionException::class);
    expect($client->requests)->toBe([]);
})->with([[[]], [['not-an-ip']]]);

it('normalizes transport and body read failures without exposing signed URLs', function (bool $readFailure) {
    $secret = 'https://media.example/?token=super-secret';
    $failure = new RuntimeException('Failed ' . $secret);
    $stream = FnStream::decorate(Utils::streamFor('x'), ['read' => function () use ($failure): never {
        throw $failure;
    }]);
    $client = remoteClient([$readFailure ? new Response(200, [], $stream) : $failure]);

    try {
        RemoteFetch::bytes($client, $secret, dnsResolver: publicRemoteDns(...));
        test()->fail('Expected transport failure');
    } catch (APIConnectionException $error) {
        expect((string) $error)->not->toContain('super-secret')->and($error->getPrevious())->toBeNull();
    }
})->with([false, true]);

it('normalizes non-success HTTP status without reading its body', function () {
    expect(fn() => RemoteFetch::bytes(remoteClient([new Response(404, [], 'secret error body')]), 'https://media.example', dnsResolver: publicRemoteDns(...)))->toThrow(NotFoundException::class);
});

it('detects stalled streams instead of looping indefinitely', function () {
    $stream = FnStream::decorate(Utils::streamFor('x'), ['read' => fn() => '', 'eof' => fn() => false]);
    expect(fn() => RemoteFetch::bytes(remoteClient([new Response(200, [], $stream)]), 'https://media.example', dnsResolver: publicRemoteDns(...)))->toThrow(APIConnectionException::class, 'stalled');
});

it('rechecks DNS on a same-host redirect', function () {
    $client = remoteClient([new Response(302, ['Location' => '/other'])]);
    $lookups = 0;
    $resolver = function () use (&$lookups): array {
        $lookups++;

        return $lookups === 1 ? ['93.184.216.34'] : ['127.0.0.1'];
    };
    expect(fn() => RemoteFetch::bytes($client, 'https://media.example', dnsResolver: $resolver))->toThrow(RemoteFetchException::class);
    expect($client->requests)->toHaveCount(1)->and($lookups)->toBe(2);
});

it('fetches public IP literals without consulting DNS', function (string $host) {
    $client = remoteClient([new Response(200, [], 'public')]);
    $result = RemoteFetch::bytes($client, 'https://' . $host . '/', dnsResolver: function (): never {
        throw new RuntimeException('DNS must not run');
    });
    expect($result->body)->toBe('public');
})->with(['93.184.216.34', '[2606:4700:4700::1111]']);

it('closes redirect response bodies without reading them', function () {
    $closed = false;
    $body = FnStream::decorate(Utils::streamFor('redirect'), [
        'read' => function (): never {
            throw new RuntimeException('Do not read redirect bodies');
        },
        'close' => function () use (&$closed): void {
            $closed = true;
        },
    ]);
    $client = remoteClient([new Response(302, ['Location' => '/final'], $body), new Response(200, [], '')]);
    expect(RemoteFetch::bytes($client, 'https://media.example', dnsResolver: publicRemoteDns(...))->body)->toBe('')
        ->and($closed)->toBeTrue();
});

it('wraps resolver failures without exposing their messages', function () {
    expect(fn() => RemoteFetch::bytes(remoteClient([]), 'https://media.example?token=secret', dnsResolver: function (): never {
        throw new RuntimeException('sensitive resolver configuration');
    }))->toThrow(APIConnectionException::class, 'Remote fetch transport failed.');
});
