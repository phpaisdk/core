<?php

declare(strict_types=1);

use AiSdk\Agent;
use AiSdk\Agents\Middleware;
use AiSdk\Agents\Middleware\StepBudget;
use AiSdk\Agents\StepContext;
use AiSdk\Agents\StepOutcome;
use AiSdk\Exceptions\ApprovalException;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\FinishReason;
use AiSdk\Observability\Events\RunFailed;
use AiSdk\Observability\Events\RunFinished;
use AiSdk\Observability\Events\RunPaused;
use AiSdk\Observability\Events\RunStarted;
use AiSdk\Observability\RunEvent;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Streaming\ErrorPart;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\ReasoningDeltaPart;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Streaming\ToolCallStartPart;
use AiSdk\Support\Usage;
use AiSdk\Testing\Fakes\FakeTextModel;
use AiSdk\Testing\Fakes\InMemoryCheckpointStore;
use AiSdk\Testing\Fakes\RecordingEvents;
use AiSdk\Tool;

it('streams provider parts and lifecycle events through the shared tool loop', function () {
    $model = FakeTextModel::scripted([new TextModelResponse([], FinishReason::Stop, Usage::empty())], [
        [new TextDeltaPart('lookup'), new ToolCallStartPart(0, 'c', 'read'), new FinishPart(FinishReason::ToolCalls, new Usage(2, 3))],
        [new ReasoningDeltaPart('reason'), new TextDeltaPart('done'), new FinishPart(FinishReason::Stop, new Usage(3, 4))],
    ]);
    $executions = 0;
    $events = new RecordingEvents();
    $stream = (new Agent($model, tools: [Tool::make('read')->run(function () use (&$executions) {
        return ++$executions;
    })]))->onEvent($events)->stream('hi');
    expect($model->requests)->toBe([])->and($stream->isComplete())->toBeFalse();
    $parts = iterator_to_array($stream);
    $run = $stream->result();
    expect($parts[0])->toBeInstanceOf(RunStarted::class)->and(end($parts))->toBeInstanceOf(RunFinished::class)
        ->and($run->result->text)->toBe('done')->and($run->result->reasoning)->toBe('reason')
        ->and($run->totalUsage->totalTokens)->toBe(12)->and($executions)->toBe(1)
        ->and($stream->run())->toBe($run)->and($stream->isComplete())->toBeTrue();
    expect(array_values(array_filter($parts, fn($part) => $part instanceof RunEvent)))->toBe($events->events);
});

it('can finish partial consumption without replaying parts or tool execution', function () {
    $model = FakeTextModel::streaming('a', 'b');
    $stream = (new Agent($model))->stream('hi');
    expect(fn() => $stream->result())->toThrow(InvalidArgumentException::class);
    $chunks = [];
    foreach ($stream->chunks() as $chunk) {
        $chunks[] = $chunk;
        break;
    }
    expect($chunks)->toBe(['a'])->and($stream->run()->result->text)->toBe('ab')->and($model->requests)->toHaveCount(1);
});

it('rejects a second active stream consumer without damaging the original', function () {
    $stream = (new Agent(FakeTextModel::streaming('ok')))->stream('hi');
    $iterator = $stream->getIterator();
    $iterator->rewind();
    expect(fn() => $stream->run())->toThrow(InvalidArgumentException::class);
    unset($iterator);
    expect($stream->run()->result->text)->toBe('ok');
});

it('streams approval announcements before pausing and resumes with the same context and budget', function () {
    $model = FakeTextModel::scripted([new TextModelResponse([], FinishReason::Stop, Usage::empty())], [
        [new TextDeltaPart('about to write'), new ToolCallStartPart(0, 'c', 'write'), new FinishPart(FinishReason::ToolCalls, new Usage(2, 3))],
        [new TextDeltaPart('done'), new FinishPart(FinishReason::Stop, new Usage(3, 4))],
    ]);
    $executions = 0;
    $agent = new Agent(
        $model,
        tools: [Tool::make('write')->needsApproval()->run(function () use (&$executions) {
            return ++$executions;
        })],
        store: new InMemoryCheckpointStore(),
        signingKey: str_repeat('x', 32),
    );
    $stream = $agent->stream('hi', 'owner');
    $parts = iterator_to_array($stream);
    $paused = $stream->result();
    expect($executions)->toBe(0)->and($paused->messages[1]->text())->toBe('about to write')
        ->and($parts[3])->toBeInstanceOf(ToolCallStartPart::class)->and(end($parts))->toBeInstanceOf(RunPaused::class);
    expect(fn() => $agent->resumeStream($paused->resumeToken, [], 'owner')->run())->toThrow(ApprovalException::class);
    $resume = $agent->resumeStream($paused->resumeToken, ['c' => true], 'owner');
    $resumedParts = iterator_to_array($resume);
    $done = $resume->result();
    expect($done->result->text)->toBe('done')->and($executions)->toBe(1)->and($done->totalUsage->totalTokens)->toBe(12)
        ->and($done->runContext->invocationId)->toBe($paused->runContext->invocationId)
        ->and(array_filter($resumedParts, fn($part) => $part instanceof RunStarted))->toBe([]);
});

it('supports middleware short circuits and budgets in streaming mode', function () {
    $middleware = new class implements Middleware {
        public function handle(StepContext $step, Closure $next): StepOutcome
        {
            return StepOutcome::fromParts([new TextPart('cached')]);
        }
    };
    $model = FakeTextModel::streaming('unused');
    $stream = (new Agent($model))->withMiddleware($middleware)->stream('hi');
    expect(iterator_to_array($stream->chunks()))->toBe(['cached'])->and($stream->result()->result->text)->toBe('cached')->and($model->requests)->toBe([]);
    $loop = FakeTextModel::scripted([new TextModelResponse([], FinishReason::Stop, Usage::empty())], [[new ToolCallStartPart(0, 'c', 'read'), new FinishPart(FinishReason::ToolCalls, Usage::empty())]]);
    $agent = (new Agent($loop, tools: [Tool::make('read')->run(fn() => 'ok')]))->withMiddleware(new StepBudget(1));
    expect($agent->stream('hi')->run()->stepLimitReached)->toBeTrue()->and($loop->requests)->toHaveCount(1);
});

it('throws stream errors once and emits a safe failure event', function (array $sequence) {
    $model = FakeTextModel::scripted([new TextModelResponse([], FinishReason::Stop, Usage::empty())], [$sequence]);
    $events = new RecordingEvents();
    $stream = (new Agent($model))->onEvent(fn() => throw new RuntimeException('listener'))->onEvent($events)->stream('hi');
    expect(fn() => $stream->run())->toThrow(InvalidResponseException::class);
    expect(fn() => $stream->result())->toThrow(InvalidResponseException::class);
    expect(array_values(array_filter($events->events, fn($event) => $event instanceof RunFailed)))->toHaveCount(1)
        ->and(array_filter($events->events, fn($event) => $event instanceof RunFinished))->toBe([])
        ->and($stream->isComplete())->toBeFalse();
})->with([
    'in stream failure' => [[new TextDeltaPart('partial'), new ErrorPart(new InvalidResponseException('private'))]],
    'empty stream' => [[]],
    'oversized step' => [[new TextDeltaPart(str_repeat('a', 16 * 1024 * 1024 + 1))]],
]);

it('forwards child activity and propagates child approvals in a streaming parent', function () {
    $childModel = FakeTextModel::scripted([
        new TextModelResponse([new \AiSdk\Responses\Parts\ToolCallPart('inside', 'write', [])], FinishReason::ToolCalls, new Usage(2, 3)),
        new TextModelResponse([new TextPart('child done')], FinishReason::Stop, new Usage(3, 4)),
    ]);
    $child = new Agent($childModel, tools: [Tool::make('write')->needsApproval()->run(fn() => 'ok')], store: new InMemoryCheckpointStore(), signingKey: str_repeat('c', 32), id: 'child');
    $parentModel = FakeTextModel::scripted([new TextModelResponse([], FinishReason::Stop, Usage::empty())], [
        [new ToolCallStartPart(0, 'delegate', 'child'), new \AiSdk\Streaming\ToolCallDeltaPart(0, '{"prompt":"work"}'), new FinishPart(FinishReason::ToolCalls, new Usage(2, 3))],
        [new TextDeltaPart('done'), new FinishPart(FinishReason::Stop, new Usage(3, 4))],
    ]);
    $parent = new Agent($parentModel, tools: [$child->asTool('child', 'Work')], store: new InMemoryCheckpointStore(), signingKey: str_repeat('p', 32), id: 'parent');
    $stream = $parent->stream('hi', 'owner');
    $parts = iterator_to_array($stream);
    $paused = $stream->result();
    $starts = array_values(array_filter($parts, fn($part) => $part instanceof RunStarted));
    expect($starts)->toHaveCount(2)->and($starts[1]->run->parentInvocationId)->toBe($starts[0]->run->invocationId);
    $done = $parent->resumeStream($paused->resumeToken, ['inside' => true], 'owner')->run();
    expect($done->result->text)->toBe('done')->and($done->totalUsage->totalTokens)->toBe(24);
});
