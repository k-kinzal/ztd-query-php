<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\ExpressionPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Partition\HashPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Partition\PartitionStrategy;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExpressionPartitioning::class)]
#[Medium]
final class ExpressionPartitioningTest extends TestCase
{
    public function testReadsAListFunction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY LIST (n) (PARTITION p VALUES IN (1))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        self::assertInstanceOf(ExpressionPartitioning::class, $partitioning->function);
        self::assertSame(PartitionStrategy::List, $partitioning->function->strategy);
    }

    public function testRejectsHash(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY HASH (id)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        self::assertInstanceOf(HashPartitioning::class, $partitioning->function);
        $this->expectException(InvalidStructure::class);
        new ExpressionPartitioning(PartitionStrategy::Hash, $partitioning->function->expression);
    }
}
