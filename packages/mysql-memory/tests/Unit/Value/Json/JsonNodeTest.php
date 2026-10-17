<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonNode::class)]
#[Small]
final class JsonNodeTest extends TestCase
{
    public function testParseReadsEveryTypeOfAJsonText(): void
    {
        $node = JsonNode::parse('{"b": [1, 2.50, "x\"\n\u0001", true, false, null, 18446744073709551615], "a": {}}');

        self::assertSame(JsonKind::Object, $node->type);
        self::assertSame('{"a": {}, "b": [1, 2.5, "x\"\n\u0001", true, false, null, 18446744073709551615]}', $node->text());
        self::assertSame([JsonKind::Object, JsonKind::Array], array_map(static fn (JsonNode $child): JsonKind => $child->type, $node->children()));
    }

    public function testParseRefusesATextThatIsNoDocument(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Invalid value.', 0));

        JsonNode::parse('abc');
    }

    public function testTextWritesTemporalAndOpaqueValuesAsStrings(): void
    {
        self::assertSame(['"2024-01-31"', '"a"', '1.50', '-0.0'], [(new JsonNode(JsonKind::Date, '2024-01-31'))->text(), (new JsonNode(JsonKind::Opaque, 'a'))->text(), (new JsonNode(JsonKind::Decimal, '1.50'))->text(), (new JsonNode(JsonKind::Double, -0.0))->text()]);
    }

    public function testUnquotedWritesTheCharactersOfAStringOnly(): void
    {
        self::assertSame(["a\nb", '[1]', 'null'], [JsonNode::parse('"a\nb"')->unquoted(), JsonNode::parse('[1]')->unquoted(), JsonNode::parse('null')->unquoted()]);
    }

    public function testScalarAnswersTheTextOfAScalarOnly(): void
    {
        self::assertSame(['12', 'x', ''], [JsonNode::parse('12')->scalar(), JsonNode::parse('"x"')->scalar(), JsonNode::parse('[1]')->scalar()]);
    }

    public function testChildrenAnswersTheElementsOrTheMembers(): void
    {
        self::assertSame([2, 2, 0], [count(JsonNode::parse('[1, 2]')->children()), count(JsonNode::parse('{"a": 1, "b": 2}')->children()), count(JsonNode::parse('3')->children())]);
    }

    public function testDescendantsAnswersEachValueBeforeTheValuesItHolds(): void
    {
        self::assertSame(['[1, [2]]', '1', '[2]', '2'], array_map(static fn (JsonNode $node): string => $node->text(), JsonNode::parse('[1, [2]]')->descendants()));
    }

    public function testEqualsComparesNumbersByValueAndOtherValuesByType(): void
    {
        $integer = new JsonNode(JsonKind::Integer, '1');

        self::assertSame(
            [true, true, false, false, true, false, false, true],
            [
                $integer->equals(JsonNode::parse('1.0')),
                $integer->equals(new JsonNode(JsonKind::Decimal, '1.00')),
                $integer->equals(JsonNode::parse('true')),
                (new JsonNode(JsonKind::String, '1'))->equals($integer),
                JsonNode::parse('{"a": [1, "x"]}')->equals(JsonNode::parse('{"a": [1.0, "x"]}')),
                JsonNode::parse('[1, 2]')->equals(JsonNode::parse('[2, 1]')),
                (new JsonNode(JsonKind::Date, '2024-01-31'))->equals(new JsonNode(JsonKind::String, '2024-01-31')),
                JsonNode::parse('null')->equals(JsonNode::parse('null')),
            ],
        );
    }

    public function testNumberWritesEqualNumbersAlike(): void
    {
        self::assertSame(['150', '0.5', '-2', '18446744073709551615'], [JsonNode::number(JsonNode::parse('1.5e2')), JsonNode::number(new JsonNode(JsonKind::Decimal, '0.50')), JsonNode::number(new JsonNode(JsonKind::Double, -2.0)), JsonNode::number(JsonNode::parse('18446744073709551615'))]);
    }

    public function testTypedMarksTheValuesWhoseTypeTheTextDoesNotTell(): void
    {
        $node = new JsonNode(JsonKind::Object, ['a' => new JsonNode(JsonKind::Decimal, '1.50'), 'b' => new JsonNode(JsonKind::Array, [new JsonNode(JsonKind::Date, '2020-01-01'), new JsonNode(JsonKind::Integer, '1')])]);

        self::assertSame('{"a": `d1.50, "b": [`D"2020-01-01", 1]}', $node->typed());
    }

    public function testMarkedTellsWhetherAValueHoldsATypedValue(): void
    {
        self::assertSame([true, false], [(new JsonNode(JsonKind::Array, [new JsonNode(JsonKind::Unsigned, '7')]))->marked(), JsonNode::parse('[1, "a"]')->marked()]);
    }

    public function testStoreAppendsTheTypedTextOnlyWhenNeeded(): void
    {
        self::assertSame(["[1.50]\0[`d1.50]", '[1.5]'], [(new JsonNode(JsonKind::Array, [new JsonNode(JsonKind::Decimal, '1.50')]))->store(), JsonNode::parse('[1.50]')->store()]);
    }

    public function testLoadReadsTheTypesBackAndTakesOtherTextAsAString(): void
    {
        $node = JsonNode::load("[1.50, \"x\"]\0[`d1.50, `O\"x\"]");

        self::assertSame([JsonKind::Decimal, JsonKind::Opaque], array_map(static fn (JsonNode $child): JsonKind => $child->type, $node->children()));
        self::assertSame([JsonKind::Double, JsonKind::String, JsonKind::Unsigned], [JsonNode::load('[1.5]')->children()[0]->type, JsonNode::load('abc')->type, JsonNode::load('18446744073709551615')->type]);
    }

    public function testNameAnswersWhatJsonTypeAnswers(): void
    {
        self::assertSame(['UNSIGNED INTEGER', 'TIMESTAMP', 'BIT', 'BLOB', 'ARRAY'], [(new JsonNode(JsonKind::Unsigned, '1'))->name(), (new JsonNode(JsonKind::Timestamp, '2020-01-01 00:00:00.000000'))->name(), (new JsonNode(JsonKind::Opaque, 'base64:type16:BQ=='))->name(), (new JsonNode(JsonKind::Opaque, 'base64:type15:AQ=='))->name(), JsonNode::parse('[]')->name()]);
    }

    public function testBitHoldsForTheOpaqueValueOfABitValue(): void
    {
        self::assertSame([true, false], [(new JsonNode(JsonKind::Opaque, 'base64:type16:BQ=='))->bit(), (new JsonNode(JsonKind::Opaque, 'base64:type252:BQ=='))->bit()]);
    }

    public function testDepthCountsTheNesting(): void
    {
        self::assertSame([1, 1, 3, 2], [JsonNode::parse('1')->depth(), JsonNode::parse('[]')->depth(), JsonNode::parse('[1, [2]]')->depth(), JsonNode::parse('{"a": {}}')->depth()]);
    }

    public function testCompareOrdersValuesAsTheServerDoes(): void
    {
        self::assertSame(
            [-1, 0, 1, 1, -1, -1, 1, 1, 0],
            [
                JsonNode::parse('null')->compare(JsonNode::parse('1')),
                JsonNode::parse('1')->compare(JsonNode::parse('1.0')),
                JsonNode::parse('"b"')->compare(JsonNode::parse('"ab"')),
                JsonNode::parse('true')->compare(JsonNode::parse('[1]')),
                JsonNode::parse('[1, 2]')->compare(JsonNode::parse('[1, 2, 0]')),
                JsonNode::parse('{"a": 1}')->compare(JsonNode::parse('{"b": 0}')),
                JsonNode::parse('{"z": 1}')->compare(JsonNode::parse('{"aa": 1}')),
                (new JsonNode(JsonKind::Time, '10:00:00.000000'))->compare(new JsonNode(JsonKind::Date, '2020-01-01')),
                (new JsonNode(JsonKind::DateTime, '2020-01-01 00:00:00.000000'))->compare(new JsonNode(JsonKind::Timestamp, '2020-01-01 00:00:00.000000')),
            ],
        );
    }

    public function testKeyIsEqualForValuesThatCompareEqual(): void
    {
        self::assertSame([true, false], [JsonNode::parse('{"a": [1, "x"]}')->key() === JsonNode::parse('{"a": [1.0, "x"]}')->key(), JsonNode::parse('"1"')->key() === JsonNode::parse('1')->key()]);
    }

    public function testRankPutsABlobAboveABitValue(): void
    {
        self::assertSame([10, 9, 5], [(new JsonNode(JsonKind::Opaque, 'base64:type15:AQ=='))->rank(), (new JsonNode(JsonKind::Opaque, 'base64:type16:AQ=='))->rank(), JsonNode::parse('true')->rank()]);
    }

    public function testElementsComparesArraysElementByElementThenByLength(): void
    {
        self::assertSame([1, -1, 0], [JsonNode::parse('[1]')->elements(JsonNode::parse('[0, 1]')), JsonNode::parse('[1]')->elements(JsonNode::parse('[1, 0]')), JsonNode::parse('[1]')->elements(JsonNode::parse('[1.0]'))]);
    }

    public function testMembersComparesObjectsBySizeThenMemberByMember(): void
    {
        self::assertSame([-1, 1, 1], [JsonNode::parse('{"b": 1}')->members(JsonNode::parse('{"a": 1, "b": 1}')), JsonNode::parse('{"a": 2, "b": 1}')->members(JsonNode::parse('{"a": 1, "c": 1}')), JsonNode::parse('{"b": 2, "aa": 1}')->members(JsonNode::parse('{"b": 1, "aa": 2}'))]);
    }
}
