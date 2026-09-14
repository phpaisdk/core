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

it('does not treat optional fields as nullable', function () {
    SchemaValidator::validate(Schema::string('name'), null);
})->throws(SchemaValidationException::class);

it('rejects additional object properties', function () {
    $schema = Schema::object('person', properties: [
        Schema::string('name')->required(),
    ]);

    SchemaValidator::validate($schema, ['name' => 'Ada', 'extra' => true]);
})->throws(SchemaValidationException::class);

it('serializes empty object properties as a JSON object', function () {
    $schema = Schema::object('filters')->jsonSchema();

    expect($schema['properties'])->toBeInstanceOf(stdClass::class);
});

it('keeps nullable enum schemas and validation aligned', function () {
    $schema = Schema::enum(['draft', 'published'], 'status')->nullable();

    expect($schema->jsonSchema()['enum'])->toBe(['draft', 'published', null])
        ->and(SchemaValidator::validate($schema, null))->toBeNull();
});
