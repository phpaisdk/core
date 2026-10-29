<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Content;
use AiSdk\Contracts\DecisionModelInterface;
use AiSdk\Contracts\Model;
use AiSdk\Decisions\Question;
use AiSdk\Requests\DecisionModelRequest;
use AiSdk\Results\DecisionResult;
use AiSdk\Support\Concerns\HasProviderOptions;

final class PendingDecisionRequest
{
    use HasProviderOptions;

    /** @var list<Question> */
    private array $questions = [];
    /** @var list<Content> */
    private array $images = [];

    /** @param string|array<string|int, mixed> $input */
    public function __construct(private string|array $input, private ?Model $model = null) {}

    public function model(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function questions(Question ...$questions): self
    {
        $names = [];
        foreach ($questions as $question) {
            if (isset($names[$question->name])) {
                throw new \InvalidArgumentException('Decision question names must be unique.');
            }
            $names[$question->name] = true;
        }
        $this->questions = array_values($questions);

        return $this;
    }

    public function images(Content ...$images): self
    {
        foreach ($images as $image) {
            if ($image->type !== Content::TYPE_IMAGE || $image->fileReference() !== null) {
                throw new \InvalidArgumentException('Decision images must be inline or URL images.');
            }
        }
        $this->images = array_values($images);

        return $this;
    }

    public function run(): DecisionResult
    {
        if ($this->questions === []) {
            throw new \InvalidArgumentException('At least one decision question is required.');
        }
        $model = ModelResolver::resolve($this->model, DecisionModelInterface::class, 'Generate::decision()');
        $response = $model->decide(new DecisionModelRequest($this->input, $this->questions, $this->images, $this->readProviderOptions()[$model->provider()] ?? []));
        if (count($response->answers) !== count($this->questions)) {
            throw new \UnexpectedValueException('Decision response must contain one answer per question.');
        }
        foreach ($this->questions as $question) {
            $answer = $response->answers[$question->name] ?? throw new \UnexpectedValueException('Decision response is missing an answer.');
            if ($answer->kind === 'refusal') {
                continue;
            }
            if ($answer->kind !== $question->kind) {
                throw new \UnexpectedValueException('Decision answer has the wrong type.');
            }
            if ($question->kind === 'choice' && (! is_string($answer->value) || ! array_key_exists($answer->value, $question->choices))) {
                throw new \UnexpectedValueException('Decision returned an unknown choice.');
            }
            if (in_array($question->kind, ['choice', 'score'], true)) {
                $expected = $question->kind === 'choice' ? array_keys($question->choices) : array_keys($question->levels);
                $actual = array_keys($answer->probabilities);
                sort($expected);
                sort($actual);
                if ($expected !== $actual || abs(array_sum($answer->probabilities) - 1.0) > 0.001) {
                    throw new \UnexpectedValueException('Decision probabilities must cover all supplied options and sum to one.');
                }
            }
            if ($question->kind === 'score' && (! is_float($answer->value) || $answer->value < 0 || $answer->value > count($question->levels) - 1)) {
                throw new \UnexpectedValueException('Decision score is outside the supplied rubric.');
            }
        }

        return new DecisionResult($response->answers, $response->usage, $response->rawResponse);
    }

    public function decide(string $question, float $threshold = 0.5): bool
    {
        \AiSdk\Decisions\Answer::probability($threshold);

        return (clone $this)->questions(Question::predicate('decision', $question))->run()->answer('decision')->yes($threshold);
    }

    /**
     * @template T
     * @param array<array-key, T> $items
     * @param callable(T): string $describe
     * @return T|null
     */
    public function choose(string $question, array $items, callable $describe, ?float $threshold = null): mixed
    {
        if ($threshold !== null) {
            \AiSdk\Decisions\Answer::probability($threshold);
        }
        $choices = [];
        foreach ($items as $key => $item) {
            $choices[$key] = $describe($item);
        }
        $answer = (clone $this)->questions(Question::choice('decision', $question, $choices))->run()->answer('decision');
        if ($answer->kind === 'refusal') {
            return null;
        }
        if (! is_string($answer->value)) {
            throw new \UnexpectedValueException('Expected a choice.');
        }
        if ($threshold !== null && $answer->probabilityOf($answer->value) < $threshold) {
            return null;
        }

        return $items[$answer->value];
    }
}
