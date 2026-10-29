<?php

declare(strict_types=1);

use AiSdk\Decisions\Answer;
use AiSdk\Decisions\Question;
use AiSdk\Generate;
use AiSdk\Responses\DecisionModelResponse;
use AiSdk\Testing\Fakes\FakeDecisionModel;

it('returns native probabilities and supports an explicit yes threshold', function () {
    $model = new FakeDecisionModel(new DecisionModelResponse(['decision' => new Answer('predicate', 0.85)]));
    expect(Generate::decision('urgent')->model($model)->decide('Is urgent?', 0.8))->toBeTrue()
        ->and(Generate::decision('urgent')->model($model)->decide('Is urgent?', 0.9))->toBeFalse();
    expect(fn() => Generate::decision('urgent')->model($model)->decide('Is urgent?', NAN))->toThrow(UnexpectedValueException::class);
    expect($model->requests)->toHaveCount(2);
});

it('selects the original item rather than returning generated data', function () {
    $billing = (object) ['label' => 'Billing'];
    $model = new FakeDecisionModel(new DecisionModelResponse(['decision' => new Answer('choice', 'billing', 0.8, ['billing' => 0.9, 'sales' => 0.1])]));
    $request = Generate::decision(['message' => 'charged twice'])->model($model);
    expect($request->choose('Route', ['billing' => $billing, 'sales' => (object) ['label' => 'Sales']], fn($item) => $item->label, 0.8))->toBe($billing);
});

it('keeps refusal distinct from false', function () {
    $model = new FakeDecisionModel(new DecisionModelResponse(['decision' => new Answer('refusal', null)]));
    expect(fn() => Generate::decision('input')->model($model)->decide('Question'))->toThrow(LogicException::class);
});

it('rejects unknown options missing answers and invalid distributions', function (array $answers) {
    $model = new FakeDecisionModel(new DecisionModelResponse($answers));
    expect(fn() => Generate::decision('input')->model($model)->questions(Question::choice('team', 'Route', ['billing' => 'Payments', 'sales' => 'Sales']))->run())->toThrow(UnexpectedValueException::class);
})->with([
    [[]],
    [['team' => new Answer('choice', 'unknown', 0.8, ['billing' => 0.5, 'sales' => 0.5])]],
    [['team' => new Answer('choice', 'billing', 0.8, ['billing' => 0.2, 'sales' => 0.2])]],
]);

it('validates question identity and score bounds', function () {
    expect(fn() => Generate::decision('input')->questions(Question::predicate('a', 'one'), Question::predicate('a', 'two')))->toThrow(InvalidArgumentException::class);
    $model = new FakeDecisionModel(new DecisionModelResponse(['score' => new Answer('score', 3.0, 0.5, [0 => 0.5, 1 => 0.5])]));
    expect(fn() => Generate::decision('input')->model($model)->questions(Question::score('score', 'How bad', ['Low', 'High']))->run())->toThrow(UnexpectedValueException::class);
});


it('selects items keyed by numeric ids', function () {
    $model = new FakeDecisionModel(new DecisionModelResponse(['decision' => new Answer('choice', '42', 0.9, [42 => 0.9, 7 => 0.1])]));
    expect(Generate::decision('input')->model($model)->choose('Pick', [42 => 'first', 7 => 'second'], fn($item) => $item))->toBe('first');
});
