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

    public function testReadMovesThePositionPastTheValue(): void
    {
        $at = 1;
        $node = JsonNode::read('[-1.5e-7, 3]', $at);

        self::assertSame([JsonKind::Double, 8], [$node->type, $at]);
    }

    public function testCharactersUndoesTheEscapesTheServerWrites(): void
    {
        $at = 0;

        self::assertSame(["a\"\\\x08\x0c\n\r\t\x01/", 24], [JsonNode::characters('"a\"\\\\\b\f\n\r\t\u0001/"', $at), $at]);
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
}
