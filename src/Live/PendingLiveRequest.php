<?php

declare(strict_types=1);

namespace AiSdk\Live;

use AiSdk\Contracts\Model;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Live\Contracts\LiveModelInterface;
use AiSdk\Live\Contracts\TransportInterface;
use AiSdk\Support\Concerns\HasProviderOptions;
use AiSdk\Support\ModelResolver;
use AiSdk\Tool;

abstract class PendingLiveRequest
{
    use HasProviderOptions;

    protected ?Model $model = null;
    /** @var array<string, mixed> */
    protected array $options = [];
    /** @var list<Tool> */
    protected array $tools = [];

    abstract protected function operation(): LiveOperation;

    public function model(Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function connect(TransportInterface $transport): LiveSession
    {
        return new LiveSession(
            $this->liveModel()->createLiveSession($this->request(), $transport),
            $this->tools,
        );
    }

    protected function option(string $key, mixed $value): static
    {
        if (is_string($value) && trim($value) === '') {
            throw new InvalidArgumentException("Live {$key} cannot be empty.");
        }

        $this->options[$key] = $value;

        return $this;
    }

    protected function request(): LiveRequest
    {
        return new LiveRequest($this->operation(), $this->options, $this->providerOptions, $this->tools);
    }

    protected function liveModel(): LiveModelInterface
    {
        return ModelResolver::resolve(
            $this->model,
            LiveModelInterface::class,
            'Live::' . $this->operation()->value . '()',
            defaultSelector: null,
        );
    }
}
