<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\ListBound;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ListBound::class)]
#[Medium]
final class ListBoundTest extends TestCase
{
    public function testReadsTuples(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY LIST COLUMNS (id, n) (PARTITION p VALUES IN ((1, 2), (3, 4)))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        $bound = $partitioning->partitions[0]->values;
        self::assertInstanceOf(ListBound::class, $bound);
        self::assertSame([2, 2], array_map(count(...), $bound->tuples));
    }

    public function testRejectsTuplesOfDifferentWidth(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY LIST COLUMNS (id, n) (PARTITION p VALUES IN ((1, 2)))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        $bound = $partitioning->partitions[0]->values;
        self::assertInstanceOf(ListBound::class, $bound);
        $this->expectException(InvalidStructure::class);
        new ListBound([$bound->tuples[0], [$bound->tuples[0][0]]]);
    }
}
