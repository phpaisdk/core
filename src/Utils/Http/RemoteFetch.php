<?php

declare(strict_types=1);

namespace AiSdk\Utils\Http;

use AiSdk\Exceptions\AiSdkException;
use AiSdk\Exceptions\APIConnectionException;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\RemoteFetchException;
use AiSdk\Support\Sdk;
use AiSdk\Utils\Errors\HttpErrorNormalizer;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

/** Bounded HTTP(S) downloads of provider-supplied media URLs. */
final class RemoteFetch
{
    private const int MAX_URL_BYTES = 8192;

    private function __construct() {}

    /**
     * Download at most maxBytes bytes, following at most three redirects. Every
     * hop rejects userinfo and non-HTTP(S) URLs and resolves all A/AAAA records;
     * non-public destinations require explicit allowPrivateNetworks opt-in.
     * The resolver must return all addresses, not merely a preferred address.
     *
     * Configure timeouts on the supplied PSR-18 client. It must expose redirects
     * (PSR-18 sendRequest semantics) and provide an unbuffered response stream
     * for transport-level memory bounds. This helper bounds its own reads even
     * if the client buffers first. PSR-18 cannot pin DNS/inspect the peer address:
     * use a client with connection-time IP enforcement against DNS rebinding,
     * and a trusted proxy if applicable. DNS validation alone is not a sandbox.
     *
     * URLs in the result may contain signed query credentials; do not log them.
     * Exceptions deliberately omit URLs and underlying transport messages.
     *
     * @param (callable(string): list<string>)|null $dnsResolver
     */
    public static function bytes(
        Sdk|ClientInterface $client,
        string $url,
        int $maxBytes = 26214400,
        bool $allowPrivateNetworks = false,
        ?callable $dnsResolver = null,
        ?RequestFactoryInterface $requestFactory = null,
    ): RemoteFetchResult {
        if ($maxBytes < 1 || $maxBytes === PHP_INT_MAX) {
            throw new InvalidArgumentException('Remote fetch maxBytes must be positive and below PHP_INT_MAX.');
        }

        try {
            $factory = $requestFactory ?? ($client instanceof Sdk ? $client->requestFactory : Psr17FactoryDiscovery::findRequestFactory());
            $http = $client instanceof Sdk ? $client->httpClient : $client;
            $resolver = $dnsResolver ?? self::resolve(...);

            for ($redirects = 0; ; $redirects++) {
                $url = self::validate($url, $allowPrivateNetworks, $resolver);

                try {
                    $response = $http->sendRequest($factory->createRequest('GET', $url)->withHeader('Accept-Encoding', 'identity'));
                } catch (Throwable) {
                    throw new APIConnectionException('Remote fetch transport failed.');
                }
                $stream = $response->getBody();

                try {
                    $status = $response->getStatusCode();
                    if (in_array($status, [301, 302, 303, 307, 308], true)) {
                        if ($redirects >= 3) {
                            throw new RemoteFetchException('Remote fetch exceeded three redirects.', ['reason' => 'too_many_redirects']);
                        }
                        $location = $response->getHeaderLine('Location');
                        if ($location === '' || strlen($location) > self::MAX_URL_BYTES || preg_match('/[\x00-\x20\x7f\\\\]/', $location) === 1) {
                            throw new InvalidArgumentException('Remote fetch redirect URL is invalid.');
                        }

                        try {
                            $redirect = parse_url($location);
                            if ($redirect === false || isset($redirect['user']) || isset($redirect['pass'])) {
                                throw new InvalidArgumentException('Remote fetch redirect URL is invalid.');
                            }
                            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
                        } catch (Throwable) {
                            throw new InvalidArgumentException('Remote fetch redirect URL is invalid.');
                        }
                        continue;
                    }
                    if ($status < 200 || $status >= 300) {
                        throw HttpErrorNormalizer::normalize('remote-fetch', $status, null);
                    }

                    $length = trim($response->getHeaderLine('Content-Length'));
                    if ($length !== '') {
                        if (! ctype_digit($length)) {
                            throw new RemoteFetchException('Remote fetch Content-Length is invalid.', ['reason' => 'invalid_response']);
                        }
                        // Compare decimal strings so huge lengths cannot overflow an integer.
                        $length = ltrim($length, '0');
                        $cap = (string) $maxBytes;
                        if (strlen($length) > strlen($cap) || (strlen($length) === strlen($cap) && strcmp($length, $cap) > 0)) {
                            throw self::oversize($maxBytes);
                        }
                    }

                    $body = self::read($stream, $maxBytes);
                    $type = trim(explode(';', $response->getHeaderLine('Content-Type'), 2)[0]);
                    if (strlen($type) > 255 || preg_match('/[\x00-\x1f\x7f]/', $type) === 1) {
                        throw new RemoteFetchException('Remote fetch Content-Type is invalid.', ['reason' => 'invalid_response']);
                    }

                    return new RemoteFetchResult($body, $type === '' ? null : $type, $url);
                } finally {
                    $stream->close();
                }
            }
        } catch (AiSdkException $error) {
            throw $error;
        } catch (Throwable) {
            // Transport errors can echo signed URLs and credentials. Do not chain them.
            throw new APIConnectionException('Remote fetch transport failed.');
        }
    }

    private static function oversize(int $maxBytes): RemoteFetchException
    {
        return new RemoteFetchException('Remote fetch exceeded the byte limit.', ['reason' => 'response_too_large', 'maxBytes' => $maxBytes]);
    }

    private static function read(StreamInterface $stream, int $maxBytes): string
    {
        $body = '';
        while (true) {
            $chunk = $stream->read(min(8192, $maxBytes - strlen($body) + 1));
            if ($chunk === '') {
                if ($stream->eof()) {
                    break;
                }

                throw new APIConnectionException('Remote fetch response stream stalled.');
            }
            if (strlen($chunk) > $maxBytes - strlen($body)) {
                throw self::oversize($maxBytes);
            }
            $body .= $chunk;
        }

        return $body;
    }

    /** @param callable(string): list<string> $resolver */
    private static function validate(string $url, bool $allowPrivateNetworks, callable $resolver): string
    {
        if (strlen($url) > self::MAX_URL_BYTES || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            throw new InvalidArgumentException('Remote fetch URL is invalid.');
        }

        try {
            $parts = parse_url($url);
        } catch (Throwable) {
            throw new InvalidArgumentException('Remote fetch URL is invalid.');
        }
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || ($parts['port'] ?? 1) < 1) {
            throw new InvalidArgumentException('Remote fetch requires an HTTP(S) URL without userinfo.');
        }
        $host = trim($parts['host'], '[]');
        if ((str_contains($parts['host'], '[') || str_contains($parts['host'], ']'))
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            throw new InvalidArgumentException('Remote fetch IPv6 host is invalid.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            // Require DNS names, excluding alternative numeric IP spellings and URI escapes.
            if (strlen($host) > 253 || preg_match('/\A(?=.{1,253}\\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)*[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\\.?\\z/i', $host) !== 1) {
                throw new InvalidArgumentException('Remote fetch host must be an IP address or DNS name.');
            }
            $addresses = $resolver($host);
        }
        if ($addresses === []) {
            throw new APIConnectionException('Remote fetch host could not be resolved.');
        }
        foreach ($addresses as $address) {
            if (! is_string($address) || filter_var($address, FILTER_VALIDATE_IP) === false) {
                throw new APIConnectionException('Remote fetch DNS returned an invalid address.');
            }
            if (! $allowPrivateNetworks && ! self::isPublic($address)) {
                throw new RemoteFetchException('Remote fetch destination is not a public network address.', ['reason' => 'blocked_network']);
            }
        }

        try {
            return (string) (new Uri($url))->withFragment('');
        } catch (Throwable) {
            throw new InvalidArgumentException('Remote fetch URL is invalid.');
        }
    }

    private static function isPublic(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        $packed = inet_pton($address);
        if ($packed === false) {
            return false;
        }
        if (strlen($packed) === 4) {
            $ip = unpack('Nip', $packed);
            if ($ip === false) {
                return false;
            }
            $value = $ip['ip'];
            foreach ([['0.0.0.0', 8], ['100.64.0.0', 10], ['168.63.129.16', 32], ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.88.99.0', 24], ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4]] as [$network, $bits]) {
                $mask = (0xffffffff << (32 - $bits)) & 0xffffffff;
                if (($value & $mask) === (ip2long($network) & $mask)) {
                    return false;
                }
            }

            return true;
        }

        // Only global unicast, excluding special-use and transition networks.
        return (ord($packed[0]) & 0xe0) === 0x20
            && ! (substr($packed, 0, 2) === "\x20\x01" && (ord($packed[2]) & 0xfe) === 0)
            && substr($packed, 0, 4) !== "\x20\x01\x0d\xb8"
            && substr($packed, 0, 2) !== "\x20\x02"
            && ! (substr($packed, 0, 2) === "\x3f\xff" && (ord($packed[2]) & 0xf0) === 0);
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            throw new APIConnectionException('Remote fetch DNS lookup failed.');
        }
        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
