<?php

declare(strict_types=1);

use AiSdk\Generate;
use AiSdk\Support\SdkFactory;
use AiSdk\Tests\Fakes\FakeEmbeddingModel;

afterEach(fn() => Generate::reset());

it('generates an embedding from text', function () {
    $model = FakeEmbeddingModel::embedding();

    $result = Generate::embedding('A sentence to embed')
        ->model($model)
        ->dimensions(256)
        ->providerOptions('fake', ['input_type' => 'document'])
        ->run();

    expect($result->output->vector)->toBe([0.1, 0.2, 0.3])
        ->and($result->output->dimensions)->toBe(3)
        ->and($result->embeddings)->toHaveCount(1)
        ->and($model->lastRequest?->inputs)->toBe(['A sentence to embed'])
        ->and($model->lastRequest?->dimensions)->toBe(256)
        ->and($model->lastRequest?->providerOptionsFor('fake'))->toBe(['input_type' => 'document']);
});

it('generates embeddings for a list of inputs', function () {
    $model = FakeEmbeddingModel::embedding();

    Generate::embedding(['First document', 'Second document'])
        ->model($model)
        ->run();

    expect($model->lastRequest?->inputs)->toBe(['First document', 'Second document']);
});

it('uses the configured default embedding model', function () {
    Generate::model(FakeEmbeddingModel::embedding([0.4, 0.5]));

    expect(Generate::embedding('Default model')->run()->output->vector)->toBe([0.4, 0.5]);
});

it('generates embeddings through an instance runtime', function () {
    $sdk = (new SdkFactory())
        ->withDefaultEmbeddingModel(FakeEmbeddingModel::embedding([0.6, 0.7]))
        ->make();

    expect($sdk->embedding('Instance runtime')->run()->output->vector)->toBe([0.6, 0.7]);
});

it('requires embedding input', function () {
    Generate::embedding()
        ->model(FakeEmbeddingModel::embedding())
        ->run();
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('rejects blank embedding input', function () {
    Generate::embedding(['valid', '   ']);
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('rejects associative embedding input', function () {
    Generate::embedding(['first' => 'document']);
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('requires an embedding model', function () {
    Generate::embedding('A sentence')->run();
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);
