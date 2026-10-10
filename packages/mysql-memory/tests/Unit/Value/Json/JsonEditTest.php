<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonEdit;
use MySqlMemory\Value\Json\JsonLeg;
use MySqlMemory\Value\Json\JsonLegKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonEdit::class)]
#[Small]
final class JsonEditTest extends TestCase
{
    public function testPutSetsInsertsOrReplacesAtAPath(): void
    {
        self::assertSame(
            ['[1, 2, 3]', '[1]', '{"a": 9}', '[1, 3]'],
            [
                JsonEdit::put(JsonNode::parse('[1, 2]'), JsonPath::parse('$[5]'), JsonNode::parse('3'), true, true)->text(),
                JsonEdit::put(JsonNode::parse('[1]'), JsonPath::parse('$[5]'), JsonNode::parse('3'), false, true)->text(),
                JsonEdit::put(JsonNode::parse('{"a": 1}'), JsonPath::parse('$.a'), JsonNode::parse('9'), false, true)->text(),
                JsonEdit::put(JsonNode::parse('1'), JsonPath::parse('$[1]'), JsonNode::parse('3'), true, false)->text(),
            ],
        );
    }

    public function testPlaceAddsOrReplacesAMember(): void
    {
        self::assertSame(['{"a": 2, "b": 1}', null], [JsonEdit::place(JsonNode::parse('{"b": 1}'), new JsonLeg(JsonLegKind::Member, 'a'), JsonNode::parse('2'), true, false)?->text(), JsonEdit::place(JsonNode::parse('{"b": 1}'), new JsonLeg(JsonLegKind::Member, 'a'), JsonNode::parse('2'), false, true)?->text()]);
    }

    public function testRemoveDropsWhatThePathNames(): void
    {
        self::assertSame(['{"b": 2}', '[2]'], [JsonEdit::remove(JsonNode::parse('{"a": 1, "b": 2}'), JsonPath::parse('$.a[0]'))->text(), JsonEdit::remove(JsonNode::parse('[1, 2]'), JsonPath::parse('$[0]'))->text()]);
    }

    public function testAppendWrapsAScalar(): void
    {
        self::assertSame(['{"a": [1, 2]}', '[1, [2, 3]]'], [JsonEdit::append(JsonNode::parse('{"a": 1}'), JsonPath::parse('$.a'), JsonNode::parse('2'))->text(), JsonEdit::append(JsonNode::parse('[1, [2]]'), JsonPath::parse('$[1]'), JsonNode::parse('3'))->text()]);
    }

    public function testInsertShiftsTheCellsAfter(): void
    {
        self::assertSame(['[1, 0, 2]', '[1, 9, 2]'], [JsonEdit::insert(JsonNode::parse('[1, 2]'), JsonPath::parse('$[last]'), JsonNode::parse('0'))->text(), JsonEdit::insert(JsonNode::parse('[1, 2]'), JsonPath::parse('$[1]'), JsonNode::parse('9'))->text()]);
    }

    public function testLocateAnswersTheStepsToAValue(): void
    {
        self::assertSame([[0, 'a'], null], [JsonEdit::locate(JsonNode::parse('[{"a": 1}]'), JsonPath::parse('$[last].a[0]')->legs), JsonEdit::locate(JsonNode::parse('[1]'), JsonPath::parse('$[3]')->legs)]);
    }

    public function testAtAnswersTheValueAtSteps(): void
    {
        self::assertSame('2', JsonEdit::at(JsonNode::parse('{"a": [1, 2]}'), ['a', 1])->text());
    }

    public function testRebuildReplacesTheValueAtSteps(): void
    {
        self::assertSame('{"a": [1, true]}', JsonEdit::rebuild(JsonNode::parse('{"a": [1, 2]}'), ['a', 1], JsonNode::parse('true'))->text());
    }

    public function testObjectOrdersMembersAsTheServerWritesThem(): void
    {
        self::assertSame('{"c": 3, "10": 2, "bb": 1}', JsonEdit::object(['bb' => JsonNode::parse('1'), '10' => JsonNode::parse('2'), 'c' => JsonNode::parse('3')])->text());
    }

    public function testNestingCountsTheArraysAndObjects(): void
    {
        self::assertSame([2, 0], [JsonEdit::nesting(JsonNode::parse('[1, [2]]')), JsonEdit::nesting(JsonNode::parse('1'))]);
    }
}
