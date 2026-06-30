<?php

declare(strict_types=1);

use AiSdk\Capability;
use AiSdk\CapabilitySupportState;
use AiSdk\ModelDefinition;
use AiSdk\Support\ModelRegistry;

it('constructs a model definition with capabilities', function () {
    $model = new ModelDefinition(
        id: 'gpt-custom',
        capabilities: [Capability::TextGeneration, Capability::Streaming],
    );

    expect($model->id)->toBe('gpt-custom')
        ->and($model->capabilities)->toBe([Capability::TextGeneration, Capability::Streaming])
        ->and($model->capabilityNames())->toBe(['text_generation', 'streaming']);
});

it('builds a model definition from raw catalog data', function () {
    $model = ModelDefinition::fromArray([
        'id' => 'gpt-4o',
        'capabilities' => ['text_generation', 'streaming', 'image_input'],
        'adapted_capabilities' => [
            'structured_output' => ['strategy' => 'json_schema'],
        ],
        'status' => 'ga',
        'limits' => ['context_window' => 128000],
    ]);

    expect($model->id)->toBe('gpt-4o')
        ->and($model->capabilities)->toBe([Capability::TextGeneration, Capability::Streaming, Capability::ImageInput])
        ->and($model->adaptedCapabilities)->toBe(['structured_output' => ['strategy' => 'json_schema']])
        ->and($model->metadata['status'])->toBe('ga')
        ->and($model->metadata['limits'])->toBe(['context_window' => 128000])
        ->and($model->capabilityNames())->toBe(['text_generation', 'streaming', 'image_input', 'structured_output']);
});

it('registers and resolves models through the model registry', function () {
    $registry = new ModelRegistry();
    $model = new ModelDefinition(id: 'gpt-custom', capabilities: [Capability::TextGeneration]);

    $registry->register('openai', $model);

    expect($registry->resolve('openai', 'gpt-custom'))->toBe($model)
        ->and($registry->resolve('openai', 'unknown-model'))->toBeNull()
        ->and($registry->resolve('anthropic', 'gpt-custom'))->toBeNull();
});

it('reports supported for registered capabilities', function () {
    $registry = new ModelRegistry();
    $registry->register('openai', new ModelDefinition(
        id: 'gpt-custom',
        capabilities: [Capability::TextGeneration, Capability::Streaming],
    ));

    $support = $registry->capability('openai', 'gpt-custom', Capability::TextGeneration);

    expect($support)->not->toBeNull()
        ->and($support->state)->toBe(CapabilitySupportState::Supported)
        ->and($support->source)->toBe('user-registered');
});

it('reports not supported for unregistered capabilities', function () {
    $registry = new ModelRegistry();
    $registry->register('openai', new ModelDefinition(
        id: 'gpt-custom',
        capabilities: [Capability::TextGeneration],
    ));

    $support = $registry->capability('openai', 'gpt-custom', Capability::ImageInput);

    expect($support)->not->toBeNull()
        ->and($support->state)->toBe(CapabilitySupportState::NotSupported)
        ->and($support->reason)->toContain('gpt-custom')
        ->and($support->reason)->toContain('ImageInput');
});

it('reports adapted capabilities from registered models', function () {
    $registry = new ModelRegistry();
    $registry->register('openai', new ModelDefinition(
        id: 'gpt-custom',
        capabilities: [Capability::TextGeneration],
        adaptedCapabilities: [
            'structured_output' => ['strategy' => 'json_schema'],
        ],
    ));

    $support = $registry->capability('openai', 'gpt-custom', Capability::StructuredOutput);

    expect($support)->not->toBeNull()
        ->and($support->state)->toBe(CapabilitySupportState::Adapted)
        ->and($support->strategy)->toBe('json_schema')
        ->and($support->source)->toBe('user-registered');
});

it('returns null for unknown models in capability lookup', function () {
    $registry = new ModelRegistry();

    expect($registry->capability('openai', 'never-registered', Capability::TextGeneration))->toBeNull();
});
