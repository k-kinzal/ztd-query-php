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
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitioningRules;
use SqlSemantics\Model\Definition\MySqlTable\Partition\RangeBound;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitioningRules::class)]
#[Medium]
final class PartitioningRulesTest extends TestCase
{
    public function testCountsRejectsSubpartitionsWithoutAFunction(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::counts(null, null, 2, []);
    }

    public function testValuesRejectsValuesForKeyPartitioning(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY RANGE (id) (PARTITION p VALUES LESS THAN (1))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        $this->expectException(InvalidStructure::class);
        PartitioningRules::values(new KeyPartitioning([]), $partitioning->partitions);
    }

    public function testSubpartitionsRejectsHashSubpartitioning(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY HASH (id)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        self::assertInstanceOf(HashPartitioning::class, $partitioning->function);
        $this->expectException(InvalidStructure::class);
        PartitioningRules::subpartitions($partitioning->function, $partitioning->function, null, []);
    }

    public function testNamesRejectsARepeatedName(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::names([new PartitionDefinition('p'), new PartitionDefinition('P')]);
    }

    public function testUnboundedRecognizesASingleMaxValue(): void
    {
        self::assertTrue(PartitioningRules::unbounded(new RangeBound([RangeBoundary::MaxValue])));
    }
}
