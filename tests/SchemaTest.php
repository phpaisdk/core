<?php

declare(strict_types=1);

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\SchemaValidationException;
use AiSdk\Schema;
use AiSdk\Support\SchemaValidator;

it('builds an object json schema with required fields', function () {
    $schema = Schema::object('address', properties: [
        Schema::string('city')->required(),
        Schema::string('country')->required(),
        Schema::string('zip'),
    ]);

    $json = $schema->jsonSchema();

    expect($json['type'])->toBe('object')
        ->and($json['required'])->toBe(['city', 'country'])
        ->and($json['additionalProperties'])->toBeFalse()
        ->and($json['properties'])->toHaveKeys(['city', 'country', 'zip']);
});

it('rejects unnamed object properties', function () {
    Schema::object('x', properties: [Schema::string()]);
})->throws(InvalidArgumentException::class);

it('validates a value against an object schema', function () {
    $schema = Schema::object('p', properties: [
        Schema::string('name')->required(),
        Schema::integer('age'),
    ]);

    $valid = SchemaValidator::validate($schema, ['name' => 'Ada', 'age' => 36]);
    expect($valid)->toBe(['name' => 'Ada', 'age' => 36]);
});

it('throws on missing required field', function () {
    $schema = Schema::object('p', properties: [Schema::string('name')->required()]);
    SchemaValidator::validate($schema, []);
})->throws(SchemaValidationException::class);

it('validates enums', function () {
    $schema = Schema::enum(['a', 'b'], 'choice')->required();
    expect(SchemaValidator::validate($schema, 'a'))->toBe('a');
});
