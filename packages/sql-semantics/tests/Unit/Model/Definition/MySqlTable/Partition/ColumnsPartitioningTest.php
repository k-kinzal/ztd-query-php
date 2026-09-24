<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\ColumnsPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Partition\PartitionStrategy;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ColumnsPartitioning::class)]
#[Medium]
final class ColumnsPartitioningTest extends TestCase
{
    public function testReadsRangeColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY RANGE COLUMNS (id, n) (PARTITION p VALUES LESS THAN (1, MAXVALUE))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        self::assertInstanceOf(ColumnsPartitioning::class, $partitioning->function);
        self::assertSame([PartitionStrategy::Range, ['id', 'n']], [$partitioning->function->strategy, $partitioning->function->columns]);
    }

    public function testRejectsHash(): void
    {
        $this->expectException(InvalidStructure::class);
        new ColumnsPartitioning(PartitionStrategy::Hash, ['id']);
    }
}
