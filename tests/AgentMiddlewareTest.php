<?php

declare(strict_types=1);

use AiSdk\Agent;
use AiSdk\Agents\Middleware;
use AiSdk\Agents\Middleware\StepBudget;
use AiSdk\Agents\Middleware\Stop;
use AiSdk\Agents\StepContext;
use AiSdk\Agents\StepOutcome;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\FinishReason;
use AiSdk\Message;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\StopWhen;
use AiSdk\Support\Usage;
use AiSdk\Testing\Fakes\FakeTextModel;
use AiSdk\Testing\Fakes\InMemoryCheckpointStore;
use AiSdk\Tool;

function stepMiddleware(Closure $handle): Middleware
{
    return new class ($handle) implements Middleware {
        public function __construct(private Closure $callback) {}
        public function handle(StepContext $step, Closure $next): StepOutcome
        {
            return ($this->callback)($step, $next);
        }
    };
}

it('wraps every generation step with first-registered middleware outermost', function () {
    $order = [];
    $layer = function (string $name) use (&$order) {
        return stepMiddleware(function (StepContext $step, Closure $next) use ($name, &$order): StepOutcome {
            $order[] = "$name:before:{$step->stepIndex}";
            $outcome = $next($step);
            $order[] = "$name:after:{$step->stepIndex}";

            return $outcome;
        });
    };
    $agent = (new Agent(FakeTextModel::scripted([
        new TextModelResponse([new ToolCallPart('c', 'lookup', [])], FinishReason::ToolCalls, Usage::empty()),
        new TextModelResponse([new TextPart('done')], FinishReason::Stop, Usage::empty()),
    ]), tools: [Tool::make('lookup')->run(fn() => 'ok')]))->withMiddleware($layer('a'), $layer('b'));
    expect($agent->run('hi')->result->text)->toBe('done');
    expect($order)->toBe(['a:before:1', 'b:before:1', 'b:after:1', 'a:after:1', 'a:before:2', 'b:before:2', 'b:after:2', 'a:after:2']);
});

it('passes immutable modified requests including tools and options to the model', function () {
    $model = FakeTextModel::text('ok');
    $tool = Tool::make('a')->run(fn() => 'ok');
    $context = null;
    $middleware = stepMiddleware(function (StepContext $step, Closure $next) use (&$context, $tool): StepOutcome {
        $context = $step;

        return $next($step->withRequest(new TextModelRequest([Message::user('edited')], system: 'edited system', tools: [$tool], maxTokens: 50, providerOptions: ['fake' => ['key' => 'value']])));
    });
    $base = new Agent($model, instructions: 'original');
    $result = $base->withMiddleware($middleware)->run('original');
    expect($context->request->messages[0]->text())->toBe('original')
        ->and($context->request->system)->toBe('original')
        ->and($model->lastRequest->messages[0]->text())->toBe('edited')
        ->and($model->lastRequest->system)->toBe('edited system')
        ->and($model->lastRequest->tools)->toBe([$tool])
        ->and($model->lastRequest->maxTokens)->toBe(50)
        ->and($model->lastRequest->providerOptionsFor('fake'))->toBe(['key' => 'value'])
        ->and($context->runContext->invocationId)->toBe($result->runContext->invocationId);
    $base->run('base still original');
    expect($model->lastRequest->system)->toBe('original');
});

it('short circuits the model with a typed outcome', function () {
    $model = FakeTextModel::text('should not run');
    $agent = (new Agent($model))->withMiddleware(stepMiddleware(fn() => StepOutcome::fromParts([new TextPart('cached')], usage: new Usage(2, 3))));
    $result = $agent->run('hi');
    expect($model->requests)->toBe([])->and($result->result->text)->toBe('cached')->and($result->totalUsage->totalTokens)->toBe(5);
});

it('rejects malformed short circuits with an SDK response exception', function (Closure $invalid) {
    $agent = (new Agent(FakeTextModel::text('unused')))->withMiddleware(stepMiddleware($invalid));
    expect(fn() => $agent->run('hi'))->toThrow(InvalidResponseException::class);
})->with([
    'wrong return type' => fn() => ['text' => 'invalid'],
    'untyped parts' => fn() => StepOutcome::fromParts([['text' => 'bad']]),
    'invalid response contents' => fn() => new StepOutcome(new TextModelResponse([new stdClass()], FinishReason::Stop, Usage::empty())),
    'duplicate ids' => fn() => StepOutcome::fromParts([new ToolCallPart('a', 't', []), new ToolCallPart('a', 't', [])]),
    'negative usage' => fn() => StepOutcome::fromParts([], usage: new Usage(-1, 0)),
    'oversized parts' => fn() => StepOutcome::fromParts(array_fill(0, 4097, new TextPart('x'))),
]);

it('propagates middleware exceptions and prevents repeated generation through next', function () {
    $failure = new InvalidArgumentException('middleware');
    $agent = (new Agent(FakeTextModel::text('unused')))->withMiddleware(stepMiddleware(fn() => throw $failure));

    try {
        $agent->run('hi');
        test()->fail('Exception was lost');
    } catch (InvalidArgumentException $error) {
        expect($error)->toBe($failure);
    }
    $model = FakeTextModel::text('ok');
    $agent = (new Agent($model))->withMiddleware(stepMiddleware(function (StepContext $step, Closure $next): StepOutcome {
        $next($step);

        return $next($step);
    }));
    expect(fn() => $agent->run('hi'))->toThrow(InvalidArgumentException::class)->and($model->requests)->toHaveCount(1);
});

it('stops before executing tools using existing StopWhen predicates', function () {
    $executed = false;
    $model = new FakeTextModel(new TextModelResponse([new TextPart('done so far'), new ToolCallPart('a', 'submit', [])], FinishReason::ToolCalls, Usage::empty()));
    $agent = (new Agent($model, tools: [Tool::make('submit')->run(function () use (&$executed) {
        $executed = true;
    })]))
        ->withMiddleware(new Stop(StopWhen::toolCalled('submit')));
    $result = $agent->run('hi');
    expect($result->result->text)->toBe('done so far')->and($executed)->toBeFalse()->and($model->requests)->toHaveCount(1)
        ->and($result->resumeToken)->toBeNull()->and($result->stepLimitReached)->toBeFalse();
});

it('preserves additional step budgets across approval resumes', function () {
    $model = new FakeTextModel(new TextModelResponse([new ToolCallPart('c', 'send', [])], FinishReason::ToolCalls, Usage::empty()));
    $agent = (new Agent($model, tools: [Tool::make('send')->needsApproval()->run(fn() => 'ok')], store: new InMemoryCheckpointStore(), signingKey: str_repeat('x', 32)))
        ->withMiddleware(new StepBudget(2));
    $first = $agent->run('hi', 'owner');
    $second = $agent->resume($first->resumeToken, ['c' => true], 'owner');
    $done = $agent->resume($second->resumeToken, ['c' => true], 'owner');
    expect($done->stepLimitReached)->toBeTrue()->and($model->requests)->toHaveCount(2);
});

it('settles tools on a budgeted last step and never raises the agent hard limit', function () {
    $executed = 0;
    $model = new FakeTextModel(new TextModelResponse([new ToolCallPart('c', 'read', [])], FinishReason::ToolCalls, Usage::empty()));
    $agent = (new Agent($model, tools: [Tool::make('read')->run(function () use (&$executed) {
        return ++$executed;
    })], maxSteps: 1))
        ->withMiddleware(new StepBudget(5));
    expect($agent->run('hi')->stepLimitReached)->toBeTrue()->and($executed)->toBe(1)->and($model->requests)->toHaveCount(1);
    expect(fn() => new StepBudget(0))->toThrow(InvalidArgumentException::class);
});
