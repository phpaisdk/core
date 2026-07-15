<?php

declare(strict_types=1);

afterEach(fn() => \AiSdk\Generate::reset());

it('resolves a provider model for the operation being run', function () {
    $provider = new class extends \AiSdk\Contracts\BaseProvider {
        public function name(): string
        {
            return 'fake';
        }

        protected function textModel(string $modelId): \AiSdk\Contracts\TextModelInterface
        {
            return \AiSdk\Tests\Fakes\FakeTextModel::text('Resolved ' . $modelId);
        }
    };

    expect(\AiSdk\Generate::text('Hello')->model($provider->model('test-model'))->run()->text)
        ->toBe('Resolved test-model')
        ->and(is_callable([$provider, 'textModel']))->toBeFalse()
        ->and(is_callable([$provider, 'resolveModel']))->toBeFalse();
});

it('accepts provider model references in capability-specific default slots', function () {
    $provider = new class extends \AiSdk\Contracts\BaseProvider {
        public function name(): string
        {
            return 'fake';
        }

        protected function textModel(string $modelId): \AiSdk\Contracts\TextModelInterface
        {
            return \AiSdk\Tests\Fakes\FakeTextModel::text('Default ' . $modelId);
        }
    };

    $sdk = (new \AiSdk\Support\SdkFactory())
        ->withDefaultTextModel($provider->model('test-model'))
        ->make();

    expect($sdk->text('Hello')->run()->text)->toBe('Default test-model');
});
