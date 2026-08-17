<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TablePartitioning;

#[CoversClass(TablePartitioning::class)]
final class TablePartitioningTest extends TestCase
{
    public function testCombinesNamedPredicatesCaseInsensitivelyAndWithoutDuplicates(): void
    {
        $partitioning = new TablePartitioning([
            'Recent' => 'created_at >= 2024',
            'Archive' => 'created_at < 2024',
        ]);

        self::assertSame(
            '(created_at >= 2024) OR (created_at < 2024)',
            $partitioning->predicateFor(['RECENT', 'archive', 'recent']),
        );
    }

    public function testReturnsNullForUnknownPartition(): void
    {
        self::assertNull((new TablePartitioning(['p0' => 'id < 10']))->predicateFor(['missing']));
    }

    public function testRejectsEmptyPartitionMetadata(): void
    {
        self::expectException(\InvalidArgumentException::class);

        new TablePartitioning(['' => 'id < 10']);
    }

    public function testRejectsEmptyPartitionPredicate(): void
    {
        self::expectException(\InvalidArgumentException::class);

        new TablePartitioning(['p0' => '  ']);
    }

    public function testRejectsWhitespacePartitionName(): void
    {
        self::expectException(\InvalidArgumentException::class);

        new TablePartitioning(['  ' => 'id < 10']);
    }
}
