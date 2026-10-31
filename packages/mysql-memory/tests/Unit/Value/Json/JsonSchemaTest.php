<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonSchema::class)]
#[Small]
final class JsonSchemaTest extends TestCase
{
    public function testRemoteFindsAReferenceOutsideTheSchema(): void
    {
        self::assertSame([true, false], [(new JsonSchema(JsonNode::parse('{"items": {"$ref": "http://x/y"}}')))->remote(), (new JsonSchema(JsonNode::parse('{"$ref": "#/definitions/a"}')))->remote()]);
    }

    public function testValidateReportsTheFirstFailureTheServerReports(): void
    {
        self::assertSame(
            [['minimum', '#/properties/a', '#/a'], ['type', '#/items', '#/1'], ['additionalProperties', '#', '#/b'], ['uniqueItems', '#', '#/2'], ['type', '#/definitions/p', '#'], ['enum', '#', '#'], null],
            [
                (new JsonSchema(JsonNode::parse('{"type": "object", "properties": {"a": {"type": "integer", "minimum": 5}}}')))->validate(JsonNode::parse('{"a": 3}')),
                (new JsonSchema(JsonNode::parse('{"type": "array", "items": {"type": "string"}}')))->validate(JsonNode::parse('["x", 1]')),
                (new JsonSchema(JsonNode::parse('{"additionalProperties": false, "properties": {"a": {}}}')))->validate(JsonNode::parse('{"a": 1, "b": 2}')),
                (new JsonSchema(JsonNode::parse('{"uniqueItems": true}')))->validate(JsonNode::parse('[1, 2, 1.0]')),
                (new JsonSchema(JsonNode::parse('{"definitions": {"p": {"type": "integer"}}, "$ref": "#/definitions/p"}')))->validate(JsonNode::parse('"x"')),
                (new JsonSchema(JsonNode::parse('{"allOf": [{"type": "string"}], "enum": [1]}')))->validate(JsonNode::parse('2')),
                (new JsonSchema(JsonNode::parse('{"format": "email", "const": 1}')))->validate(JsonNode::parse('"x"')),
            ],
        );
    }

    public function testCheckValidatesAValueAtItsLocations(): void
    {
        self::assertSame(['maxLength', '#/x', '#/y'], (new JsonSchema(JsonNode::parse('{}')))->check(JsonNode::parse('{"maxLength": 2}'), '#/x', JsonNode::parse('"abc"'), '#/y'));
    }

    public function testKeywordsAnswersTheMembersOfAnObjectOnly(): void
    {
        self::assertSame([['type'], []], [array_keys(JsonSchema::keywords(JsonNode::parse('{"type": "string"}'))), JsonSchema::keywords(JsonNode::parse('true'))]);
    }

    public function testPointerFollowsAReferenceInTheSchema(): void
    {
        $schema = new JsonSchema(JsonNode::parse('{"definitions": {"a/b": {"type": "string"}}}'));

        $found = $schema->pointer('#/definitions/a~1b');

        self::assertNotNull($found);
        self::assertSame(['{"type": "string"}', '#/definitions/a~1b'], [$found[0]->text(), $found[1]]);
        self::assertNull($schema->pointer('#/definitions/none'));
    }

    public function testTypedMatchesTheTypesOfJsonSchema(): void
    {
        self::assertSame(
            [true, false, true, true, false],
            [JsonSchema::typed(JsonNode::parse('"integer"'), JsonNode::parse('18446744073709551615')), JsonSchema::typed(JsonNode::parse('"integer"'), JsonNode::parse('1.0')), JsonSchema::typed(JsonNode::parse('["string", "null"]'), JsonNode::parse('null')), JsonSchema::typed(JsonNode::parse('"number"'), JsonNode::parse('1')), JsonSchema::typed(JsonNode::parse('"foo"'), JsonNode::parse('1'))],
        );
    }

    public function testNumberChecksTheBoundsAndTheMultipleExactly(): void
    {
        $schema = new JsonSchema(JsonNode::parse('{}'));

        self::assertSame(
            [null, ['maximum', '#', '#'], ['minimum', '#', '#'], null, ['multipleOf', '#', '#']],
            [
                $schema->number(JsonSchema::keywords(JsonNode::parse('{"multipleOf": 0.1}')), '#', JsonNode::parse('0.3'), '#'),
                $schema->number(JsonSchema::keywords(JsonNode::parse('{"maximum": 1.5, "exclusiveMaximum": true}')), '#', JsonNode::parse('1.5'), '#'),
                $schema->number(JsonSchema::keywords(JsonNode::parse('{"maximum": 1, "minimum": 5}')), '#', JsonNode::parse('2'), '#'),
                $schema->number(JsonSchema::keywords(JsonNode::parse('{"multipleOf": -2}')), '#', JsonNode::parse('5'), '#'),
                $schema->number(JsonSchema::keywords(JsonNode::parse('{"multipleOf": 2.5}')), '#', JsonNode::parse('7'), '#'),
            ],
        );
    }

    public function testStringCountsCharactersAndIgnoresABadPattern(): void
    {
        $schema = new JsonSchema(JsonNode::parse('{}'));

        self::assertSame([null, ['minLength', '#', '#'], null], [$schema->string(JsonSchema::keywords(JsonNode::parse('{"maxLength": 1}')), '#', JsonNode::parse('"é"'), '#'), $schema->string(JsonSchema::keywords(JsonNode::parse('{"minLength": 5, "maxLength": 1}')), '#', JsonNode::parse('"ab"'), '#'), $schema->string(JsonSchema::keywords(JsonNode::parse('{"pattern": "("}')), '#', JsonNode::parse('"a"'), '#')]);
    }

    public function testMatchesSearchesTheString(): void
    {
        self::assertSame([true, false, null], [JsonSchema::matches('^\\p{L}+$', 'é'), JsonSchema::matches('^a', 'ba'), JsonSchema::matches('(', 'a')]);
    }

    public function testCountReadsANonNegativeInteger(): void
    {
        self::assertSame([3, null, null, null], [JsonSchema::count(JsonNode::parse('3')), JsonSchema::count(JsonNode::parse('1.5')), JsonSchema::count(JsonNode::parse('"3"')), JsonSchema::count(JsonNode::parse('-1'))]);
    }

    public function testObjectChecksTheMembersBeforeTheObject(): void
    {
        $schema = new JsonSchema(JsonNode::parse('{}'));

        self::assertSame(
            [['type', '#/properties/a', '#/a'], ['required', '#', '#'], ['patternProperties', '#', '#/ab'], ['type', '#/additionalProperties', '#/a'], ['dependencies', '#', '#']],
            [
                $schema->object(JsonSchema::keywords(JsonNode::parse('{"required": ["x"], "properties": {"a": {"type": "string"}}}')), '#', JsonNode::parse('{"a": 1}'), '#'),
                $schema->object(JsonSchema::keywords(JsonNode::parse('{"minProperties": 3, "required": ["x"]}')), '#', JsonNode::parse('{"a": 1}'), '#'),
                $schema->object(JsonSchema::keywords(JsonNode::parse('{"properties": {"ab": {"type": "string"}}, "patternProperties": {"^a": {"type": "integer"}}}')), '#', JsonNode::parse('{"ab": 1}'), '#'),
                $schema->object(JsonSchema::keywords(JsonNode::parse('{"maxProperties": 0, "additionalProperties": {"type": "string"}}')), '#', JsonNode::parse('{"a": 1}'), '#'),
                $schema->object(JsonSchema::keywords(JsonNode::parse('{"dependencies": {"a": ["b"]}}')), '#', JsonNode::parse('{"a": 1}'), '#'),
            ],
        );
    }

    public function testMemberChecksTheSchemasOfAMember(): void
    {
        $schema = new JsonSchema(JsonNode::parse('{}'));

        self::assertSame(
            [['additionalProperties', '#', '#/b'], null, ['patternProperties', '#', '#/ab'], ['type', '#/properties/a', '#/a'], ['type', '#/additionalProperties', '#/c'], null],
            [
                $schema->member(JsonSchema::keywords(JsonNode::parse('{"properties": {"a": {}}, "additionalProperties": false}')), '#', 'b', JsonNode::parse('1'), '#/b'),
                $schema->member(JsonSchema::keywords(JsonNode::parse('{"patternProperties": {"^b": {}}, "additionalProperties": false}')), '#', 'b', JsonNode::parse('1'), '#/b'),
                $schema->member(JsonSchema::keywords(JsonNode::parse('{"properties": {"ab": {"type": "string"}}, "patternProperties": {"^a": {"type": "integer"}}}')), '#', 'ab', JsonNode::parse('1'), '#/ab'),
                $schema->member(JsonSchema::keywords(JsonNode::parse('{"properties": {"a": {"type": "string"}}}')), '#', 'a', JsonNode::parse('1'), '#/a'),
                $schema->member(JsonSchema::keywords(JsonNode::parse('{"additionalProperties": {"type": "string"}}')), '#', 'c', JsonNode::parse('1'), '#/c'),
                $schema->member(JsonSchema::keywords(JsonNode::parse('{"patternProperties": {"^c": {"type": "integer"}}, "additionalProperties": {"type": "string"}}')), '#', 'c', JsonNode::parse('1'), '#/c'),
            ],
        );
    }

    public function testPropertiesChecksTheObjectAsAWhole(): void
    {
        $schema = new JsonSchema(JsonNode::parse('{}'));

        self::assertSame(
            [['required', '#', '#'], ['minProperties', '#', '#'], ['maxProperties', '#', '#'], ['dependencies', '#', '#'], null],
            [
                $schema->properties(JsonSchema::keywords(JsonNode::parse('{"minProperties": 3, "required": ["x"]}')), '#', JsonNode::parse('{"a": 1}'), '#'),
                $schema->properties(JsonSchema::keywords(JsonNode::parse('{"minProperties": 2, "maxProperties": 0}')), '#', JsonNode::parse('{"a": 1}'), '#'),
                $schema->properties(JsonSchema::keywords(JsonNode::parse('{"maxProperties": 0}')), '#', JsonNode::parse('{"a": 1}'), '#'),
                $schema->properties(JsonSchema::keywords(JsonNode::parse('{"dependencies": {"a": {"required": ["b"]}}}')), '#', JsonNode::parse('{"a": 1}'), '#'),
                $schema->properties(JsonSchema::keywords(JsonNode::parse('{"required": ["a"], "dependencies": {"b": ["c"]}}')), '#', JsonNode::parse('{"a": 1}'), '#'),
            ],
        );
    }

    public function testArrayChecksTheElementsBeforeTheArray(): void
    {
        $schema = new JsonSchema(JsonNode::parse('{}'));

        self::assertSame(
            [['type', '#/items/0', '#/0'], ['additionalItems', '#', '#/1'], ['uniqueItems', '#', '#/1'], ['maxItems', '#', '#']],
            [
                $schema->array(JsonSchema::keywords(JsonNode::parse('{"items": [{"type": "string"}]}')), '#', JsonNode::parse('[1]'), '#'),
                $schema->array(JsonSchema::keywords(JsonNode::parse('{"items": [{"type": "string"}], "additionalItems": false}')), '#', JsonNode::parse('["a", 1]'), '#'),
                $schema->array(JsonSchema::keywords(JsonNode::parse('{"minItems": 5, "uniqueItems": true}')), '#', JsonNode::parse('[1, 1]'), '#'),
                $schema->array(JsonSchema::keywords(JsonNode::parse('{"maxItems": 1}')), '#', JsonNode::parse('[1, 2]'), '#'),
            ],
        );
    }

    public function testCombinedChecksEnumThenTheCombinations(): void
    {
        $schema = new JsonSchema(JsonNode::parse('{}'));

        self::assertSame(
            [['allOf', '#', '#'], ['oneOf', '#', '#'], ['not', '#', '#'], null],
            [
                $schema->combined(JsonSchema::keywords(JsonNode::parse('{"anyOf": [{"type": "string"}], "oneOf": [{"type": "string"}], "allOf": [{"type": "string"}]}')), '#', JsonNode::parse('2'), '#'),
                $schema->combined(JsonSchema::keywords(JsonNode::parse('{"oneOf": [{"type": "integer"}, {"minimum": 0}]}')), '#', JsonNode::parse('5'), '#'),
                $schema->combined(JsonSchema::keywords(JsonNode::parse('{"not": {"type": "string"}}')), '#', JsonNode::parse('"x"'), '#'),
                $schema->combined(JsonSchema::keywords(JsonNode::parse('{"enum": [{"a": [1]}]}')), '#', JsonNode::parse('{"a": [1.0]}'), '#'),
            ],
        );
    }

    public function testReportWritesTheObjectTheServerAnswers(): void
    {
        self::assertSame(['{"valid": true}', '{"valid": false, "reason": "The JSON document location \'#/a\' failed requirement \'type\' at JSON Schema location \'#/properties/a\'", "schema-location": "#/properties/a", "document-location": "#/a", "schema-failed-keyword": "type"}'], [JsonSchema::report(null)->text(), JsonSchema::report(['type', '#/properties/a', '#/a'])->text()]);
    }
}
