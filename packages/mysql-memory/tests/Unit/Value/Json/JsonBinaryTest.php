<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonBinary;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonBinary::class)]
#[Small]
final class JsonBinaryTest extends TestCase
{
    public function testSizeAnswersWhatJsonStorageSizeAnswers(): void
    {
        self::assertSame([2, 3, 5, 9, 13, 8, 12, 141], [JsonBinary::size(JsonNode::parse('true')), JsonBinary::size(JsonNode::parse('1')), JsonBinary::size(JsonNode::parse('70000')), JsonBinary::size(JsonNode::parse('1.5')), JsonBinary::size(JsonNode::parse('{"a": null}')), JsonBinary::size(JsonNode::parse('[1]')), JsonBinary::size(JsonNode::parse('["abc"]')), JsonBinary::size(new JsonNode(JsonKind::Array, [new JsonNode(JsonKind::Opaque, 'base64:type15:' . base64_encode(str_repeat("\x01", 130)))]))]);
    }

    public function testValueCountsTheBytesAfterTheEntries(): void
    {
        self::assertSame([0, 3, 8, 10], [JsonBinary::value(JsonNode::parse('null')), JsonBinary::value(JsonNode::parse('"ab"')), JsonBinary::value(JsonNode::parse('1e3')), JsonBinary::value(new JsonNode(JsonKind::Date, '2020-01-01'))]);
    }

    public function testContainerTakesTheLargeFormBeyond65535Bytes(): void
    {
        self::assertSame([4, 70016], [JsonBinary::container(JsonNode::parse('{}')), JsonBinary::container(new JsonNode(JsonKind::Array, [new JsonNode(JsonKind::String, str_repeat('x', 70000))]))]);
    }

    public function testFormCountsTheSmallAndTheLargeForm(): void
    {
        self::assertSame([9, 15], [JsonBinary::form(JsonNode::parse('["a"]'), false), JsonBinary::form(JsonNode::parse('["a"]'), true)]);
    }

    public function testInlinedHoldsSmallIntegersAndLiteralsInTheirEntries(): void
    {
        self::assertSame([true, false, true, false], [JsonBinary::inlined(JsonNode::parse('true'), false), JsonBinary::inlined(JsonNode::parse('65536'), false), JsonBinary::inlined(JsonNode::parse('65536'), true), JsonBinary::inlined(JsonNode::parse('"a"'), true)]);
    }

    public function testIntegerTakesTheFewestBytes(): void
    {
        self::assertSame([2, 4, 8, 2, 4], [JsonBinary::integer(JsonNode::parse('-32768')), JsonBinary::integer(JsonNode::parse('32768')), JsonBinary::integer(JsonNode::parse('2147483648')), JsonBinary::integer(new JsonNode(JsonKind::Unsigned, '65535')), JsonBinary::integer(new JsonNode(JsonKind::Unsigned, '65536'))]);
    }

    public function testOpaqueCountsTheTypeTheLengthAndTheBytes(): void
    {
        self::assertSame([3, 203], [JsonBinary::opaque(1), JsonBinary::opaque(200)]);
    }

    public function testLengthWritesSevenBitsToAByte(): void
    {
        self::assertSame([1, 2, 2, 3], [JsonBinary::length(127), JsonBinary::length(128), JsonBinary::length(16383), JsonBinary::length(16384)]);
    }

    public function testDecimalCountsThePackedDigits(): void
    {
        self::assertSame([5, 1, 1], [JsonBinary::decimal('-12.50'), JsonBinary::decimal('0'), JsonBinary::decimal('0.5')]);
    }

    public function testDigitsPacksNineDigitsInFourBytes(): void
    {
        self::assertSame([0, 1, 4, 5], [JsonBinary::digits(0), JsonBinary::digits(1), JsonBinary::digits(9), JsonBinary::digits(10)]);
    }
}
