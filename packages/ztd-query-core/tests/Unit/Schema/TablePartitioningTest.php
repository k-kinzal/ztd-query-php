<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
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
            ['created_at >= 2024', 'created_at < 2024'],
            $partitioning->predicatesFor(['RECENT', 'archive', 'recent']),
        );
    }

    public function testReturnsNullForUnknownPartition(): void
    {
        self::assertNull((new TablePartitioning(['p0' => 'id < 10']))->predicatesFor(['missing']));
    }

    public function testRejectsEmptyPartitionMetadata(): void
    {
        self::expectException(InvalidDefinitionException::class);

        new TablePartitioning(['' => 'id < 10']);
    }

    public function testRejectsEmptyPartitionPredicate(): void
    {
        self::expectException(InvalidDefinitionException::class);

        new TablePartitioning(['p0' => '  ']);
    }

    public function testRejectsWhitespacePartitionName(): void
    {
        self::expectException(InvalidDefinitionException::class);

        new TablePartitioning(['  ' => 'id < 10']);
    }

    public function testPredicatesForAnswersThePredicateOfEveryPartitionNamed(): void
    {
        $partitioning = new TablePartitioning(['p0' => 'id < 10', 'p1' => 'id >= 10']);

        self::assertSame(['id < 10'], $partitioning->predicatesFor(['p0']));
    }

    public function testPredicatesForIsNothingWhereAPartitionIsNotOneOfThem(): void
    {
        $partitioning = new TablePartitioning(['p0' => 'id < 10']);

        self::assertNull($partitioning->predicatesFor(['p9']));
    }
}
