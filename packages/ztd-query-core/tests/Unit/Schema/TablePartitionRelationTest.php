<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TablePartitionRelation;

#[CoversClass(TablePartitionRelation::class)]
final class TablePartitionRelationTest extends TestCase
{
    public function testSpecificPartitionUsesItsPredicate(): void
    {
        $relation = new TablePartitionRelation('events', 'created_at >= DATE \'2024-01-01\'');

        self::assertSame('events', $relation->parentTable);
        self::assertSame('created_at >= DATE \'2024-01-01\'', $relation->selectionPredicate(['FALSE']));
    }

    public function testDefaultPartitionExcludesSpecificSiblingsAndIncludesNull(): void
    {
        $relation = new TablePartitionRelation('events', null);

        self::assertSame(
            'COALESCE(NOT ((year = 2024) OR (year = 2025)), TRUE)',
            $relation->selectionPredicate(['year = 2024', 'year = 2025']),
        );
        self::assertSame('TRUE', $relation->selectionPredicate([]));
    }

    public function testRejectsEmptyParentAndPredicate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TablePartitionRelation('', 'id = 1');
    }

    public function testRejectsWhitespaceOnlyParent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TablePartitionRelation('  ', 'id = 1');
    }

    public function testRejectsBlankPredicate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TablePartitionRelation('events', ' ');
    }
}
