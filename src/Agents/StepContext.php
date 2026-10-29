<?php

declare(strict_types=1);

namespace AiSdk\Agents;

use AiSdk\Observability\RunContext;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Support\Usage;

final readonly class StepContext
{
    public function __construct(
        public TextModelRequest $request,
        public RunContext $runContext,
        public int $stepIndex,
        public Usage $totalUsage = new Usage(),
    ) {
        if ($request->messages === []) {
            throw new \AiSdk\Exceptions\InvalidArgumentException('A step request requires messages.');
        }
        foreach ($request->messages as $message) {
            if (! $message instanceof \AiSdk\Message) {
                throw new \AiSdk\Exceptions\InvalidArgumentException('Step messages must be Message instances.');
            }
        }
        $names = [];
        foreach ($request->tools as $tool) {
            if (! $tool instanceof \AiSdk\Tool || $tool->name() === '' || isset($names[$tool->name()])) {
                throw new \AiSdk\Exceptions\InvalidArgumentException('Step tools must have unique nonempty names.');
            }
            $names[$tool->name()] = true;
        }
    }

    public function withRequest(TextModelRequest $request): self
    {
        return new self($request, $this->runContext, $this->stepIndex, $this->totalUsage);
    }
}
