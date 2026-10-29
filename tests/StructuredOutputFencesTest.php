<?php

declare(strict_types=1);

use AiSdk\Exceptions\JsonException;
use AiSdk\Generate;
use AiSdk\Outputs\Output;
use AiSdk\Schema;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Tests\Fakes\FakeTextModel;

afterEach(fn() => Generate::reset());

it('decodes one surrounding fence without changing JSON string contents', function (string $text) {
    $fake = FakeTextModel::text($text);
    $model = FakeTextModel::scripted([$fake->generate(new AiSdk\Requests\TextModelRequest([]))], [[new TextDeltaPart($text)]]);
    $request = Generate::text('hi')->model($model)->output(Output::schema(Schema::object('result', properties: [Schema::string('value')])));
    expect($request->run()->output)->toBe(['value' => '```json inside ```']);
    $stream = $request->stream();
    foreach ($stream as $part) {
        // Consume the complete structured output.
    }
    expect($stream->result()->output)->toBe(['value' => '```json inside ```']);
})->with([
    ['```' . "\n" . '{"value":"```json inside ```"}' . "\n```"],
    ["\n```json\n" . '{"value":"```json inside ```"}' . "\n```\n"],
    ["\r\n```json\r\n" . '{"value":"```json inside ```"}' . "\r\n```\r\n"],
    ['{"value":"```json inside ```"}'],
]);

it('keeps malformed fenced JSON as a typed decoding failure', function () {
    expect(fn() => Generate::text('hi')->model(FakeTextModel::text("```json\n{broken}\n```"))
        ->output(Output::schema(Schema::object('result', properties: [Schema::string('value')])))->run())->toThrow(JsonException::class);
});
