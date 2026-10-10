<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonLeg;
use MySqlMemory\Value\Json\JsonLegKind;
use MySqlMemory\Value\Json\JsonNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonLeg::class)]
#[Small]
final class JsonLegTest extends TestCase
{
    public function testApplySelectsMembersCellsAndRanges(): void
    {
        $array = JsonNode::parse('[1, 2, 3]');
        $object = JsonNode::parse('{"a": 1, "b": [2]}');

        self::assertSame(['1'], array_map(static fn (JsonNode $node): string => $node->text(), (new JsonLeg(JsonLegKind::Member, 'a'))->apply($object)));
        self::assertSame([], array_map(static fn (JsonNode $node): string => $node->text(), (new JsonLeg(JsonLegKind::Member, 'a'))->apply($array)));
        self::assertSame(['1', '[2]'], array_map(static fn (JsonNode $node): string => $node->text(), (new JsonLeg(JsonLegKind::AnyMember))->apply($object)));
        self::assertSame(['2'], array_map(static fn (JsonNode $node): string => $node->text(), (new JsonLeg(JsonLegKind::Cell, '', [true, 1]))->apply($array)));
        self::assertSame([], array_map(static fn (JsonNode $node): string => $node->text(), (new JsonLeg(JsonLegKind::Cell, '', [true, 5]))->apply($array)));
        self::assertSame(['{"a": 1, "b": [2]}'], array_map(static fn (JsonNode $node): string => $node->text(), (new JsonLeg(JsonLegKind::Cell))->apply($object)));
        self::assertSame([], array_map(static fn (JsonNode $node): string => $node->text(), (new JsonLeg(JsonLegKind::AnyCell))->apply($object)));
        self::assertSame(['1', '2', '3'], array_map(static fn (JsonNode $node): string => $node->text(), (new JsonLeg(JsonLegKind::Range, '', [true, 5], [false, 9]))->apply($array)));
        self::assertSame([], array_map(static fn (JsonNode $node): string => $node->text(), (new JsonLeg(JsonLegKind::Range, '', [false, 2], [true, 1]))->apply($array)));
        self::assertSame(['{"a": 1, "b": [2]}', '1', '[2]', '2'], array_map(static fn (JsonNode $node): string => $node->text(), (new JsonLeg(JsonLegKind::Descendants))->apply($object)));
    }

    public function testCellCountsFromTheFirstOrBackFromTheLastCell(): void
    {
        self::assertSame([2, 0, -2], [(new JsonLeg(JsonLegKind::Cell))->cell([false, 2], 3), (new JsonLeg(JsonLegKind::Cell))->cell([true, 0], 1), (new JsonLeg(JsonLegKind::Cell))->cell([true, 4], 3)]);
    }
}
