<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\KeyPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(KeyPartitioning::class)]
#[Medium]
final class KeyPartitioningTest extends TestCase
{
    public function testReadsAnEmptyColumnList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY KEY () PARTITIONS 2');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        self::assertInstanceOf(KeyPartitioning::class, $partitioning->function);
        self::assertSame([], $partitioning->function->columns);
        self::assertNull($partitioning->function->algorithm);
    }

    public function testRejectsARepeatedColumn(): void
    {
        $this->expectException(InvalidStructure::class);
        new KeyPartitioning(['a', 'a']);
    }
}
