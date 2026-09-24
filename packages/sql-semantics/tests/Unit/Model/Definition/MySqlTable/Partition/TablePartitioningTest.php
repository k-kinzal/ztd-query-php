<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\HashPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Partition\KeyPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionDefinition;
use SqlSemantics\Model\Definition\MySqlTable\Partition\TablePartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TablePartitioning::class)]
#[Medium]
final class TablePartitioningTest extends TestCase
{
    public function testReadsCountsAndSubpartitioning(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY RANGE (id) SUBPARTITION BY HASH (id) SUBPARTITIONS 2 (PARTITION p VALUES LESS THAN MAXVALUE)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        self::assertSame(2, $partitioning->subpartitionCount);
        self::assertInstanceOf(HashPartitioning::class, $partitioning->subpartitioning);
    }

    public function testRejectsACountThatDisagreesWithTheDefinitions(): void
    {
        $this->expectException(InvalidStructure::class);
        new TablePartitioning(new KeyPartitioning([]), 3, null, null, [new PartitionDefinition('p')]);
    }
}
