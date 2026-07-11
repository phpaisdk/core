<?php

declare(strict_types=1);

use AiSdk\Capability;
use AiSdk\ModelDefinition;

it('constructs model information returned by provider discovery', function () {
    $model = new ModelDefinition(
        id: 'local-model',
        capabilities: [Capability::TextGeneration, Capability::Streaming],
        metadata: ['family' => 'custom'],
    );

    expect($model->id)->toBe('local-model')
        ->and($model->capabilityNames())->toBe(['text_generation', 'streaming'])
        ->and($model->metadata)->toBe(['family' => 'custom']);
});

it('builds model information from runtime discovery data', function () {
    $model = ModelDefinition::fromArray([
        'id' => 'local-model',
        'capabilities' => ['text_generation', 'streaming', 'image_input', 'embeddings'],
        'adapted_capabilities' => [
            'structured_output' => ['strategy' => 'response format'],
        ],
        'status' => 'available',
        'limits' => ['context_window' => 128000],
    ]);

    expect($model->capabilities)->toBe([
        Capability::TextGeneration,
        Capability::Streaming,
        Capability::ImageInput,
        Capability::Embedding,
    ])->and($model->adaptedCapabilities)->toBe([
        'structured_output' => ['strategy' => 'response format'],
    ])->and($model->metadata['status'])->toBe('available')
        ->and($model->metadata['limits'])->toBe(['context_window' => 128000]);
});
