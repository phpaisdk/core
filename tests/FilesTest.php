<?php

declare(strict_types=1);

use AiSdk\Files\FileReference;
use AiSdk\Files\FileUpload;
use AiSdk\Testing\Fakes\FakeFileStore;
use Nyholm\Psr7\Factory\Psr17Factory;

it('does not interpret string upload content as a filesystem path', function () {
    $upload = new FileUpload('/etc/passwd', 'example.txt');
    expect((string) $upload->stream(new Psr17Factory(), 100))->toBe('/etc/passwd');
});

it('rejects unsafe upload headers and oversized streams', function () {
    expect(fn() => new FileUpload('bytes', "a\r\nInjected: true"))->toThrow(\AiSdk\Exceptions\InvalidArgumentException::class);
    expect(fn() => (new FileUpload('1234', 'a.txt'))->stream(new Psr17Factory(), 3))->toThrow(\AiSdk\Exceptions\InvalidArgumentException::class);
    expect(fn() => (new FileReference('anthropic', 'file-1'))->forProvider('openai'))->toThrow(\AiSdk\Exceptions\InvalidArgumentException::class);
});

it('provides deterministic upload download pagination and deletion fakes', function () {
    $store = new FakeFileStore(new Psr17Factory());
    $first = $store->upload(new FileUpload('a', 'a.txt'));
    $second = $store->upload(new FileUpload('b', 'b.txt'));
    $page = $store->list(limit: 1);
    expect($page->files[0])->toBe($first)->and($store->list($page->nextCursor)->files[0])->toBe($second)
        ->and((string) $store->download($first->reference->id))->toBe('a');
    $store->delete($first->reference->id);
    expect(fn() => $store->retrieve($first->reference->id))->toThrow(OutOfBoundsException::class);
});
