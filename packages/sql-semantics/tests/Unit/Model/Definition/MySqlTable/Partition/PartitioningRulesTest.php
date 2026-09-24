<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\ColumnsPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Partition\HashPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Partition\KeyPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionDefinition;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitioningRules;
use SqlSemantics\Model\Definition\MySqlTable\Partition\RangeBound;
use SqlSemantics\Model\Definition\MySqlTable\Partition\SubpartitionDefinition;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Partition\PartitionStrategy;
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

    #[TestWith([0, null])]
    #[TestWith([8193, null])]
    #[TestWith([null, 0])]
    #[TestWith([null, 8193])]
    public function testCountsRejectsACountOutsideTheServerRange(?int $partitionCount, ?int $subpartitionCount): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::counts($partitionCount, new KeyPartitioning(['id']), $subpartitionCount, []);
    }

    #[TestWith([1, 1])]
    #[TestWith([8192, 8192])]
    public function testCountsAcceptsTheServerRangeBoundaries(int $partitionCount, int $subpartitionCount): void
    {
        $this->expectNotToPerformAssertions();
        PartitioningRules::counts($partitionCount, new KeyPartitioning(['id']), $subpartitionCount, []);
    }

    public function testCountsRejectsAPartitionCountThatDisagreesWithTheDefinitions(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::counts(2, null, null, [new PartitionDefinition('p')]);
    }

    public function testCountsAcceptsAPartitionCountThatAgreesWithTheDefinitions(): void
    {
        $this->expectNotToPerformAssertions();
        PartitioningRules::counts(2, null, null, [new PartitionDefinition('p'), new PartitionDefinition('q')]);
    }

    public function testValuesRejectsRangePartitioningWithoutDefinitions(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::values(new ColumnsPartitioning(PartitionStrategy::Range, ['id']), []);
    }

    public function testValuesAcceptsKeyPartitionsWithoutValues(): void
    {
        $this->expectNotToPerformAssertions();
        PartitioningRules::values(new KeyPartitioning(['id']), [new PartitionDefinition('p')]);
    }

    public function testValuesRejectsARangeBoundNarrowerThanTheColumns(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::values(new ColumnsPartitioning(PartitionStrategy::Range, ['id', 'n']), [new PartitionDefinition('p', new RangeBound([RangeBoundary::MaxValue]))]);
    }

    public function testValuesAcceptsARangeBoundAsWideAsTheColumns(): void
    {
        $this->expectNotToPerformAssertions();
        PartitioningRules::values(new ColumnsPartitioning(PartitionStrategy::Range, ['id', 'n']), [new PartitionDefinition('p', new RangeBound([RangeBoundary::MaxValue, RangeBoundary::MaxValue]))]);
    }

    public function testValuesRejectsListTuplesWiderThanTheColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY LIST COLUMNS (id, n) (PARTITION p VALUES IN ((1, 2), (3, 4)))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $this->expectException(InvalidStructure::class);
        PartitioningRules::values(new ColumnsPartitioning(PartitionStrategy::List, ['id']), $alteration->partitioning->partitions);
    }

    public function testValuesRejectsListValuesUnderRangePartitioning(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY LIST COLUMNS (id, n) (PARTITION p VALUES IN ((1, 2), (3, 4)))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $this->expectException(InvalidStructure::class);
        PartitioningRules::values(new ColumnsPartitioning(PartitionStrategy::Range, ['id', 'n']), $alteration->partitioning->partitions);
    }

    public function testValuesRejectsRangeValuesUnderListPartitioning(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::values(new ColumnsPartitioning(PartitionStrategy::List, ['id']), [new PartitionDefinition('p', new RangeBound([RangeBoundary::MaxValue]))]);
    }

    public function testValuesAcceptsListTuplesAsWideAsTheColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY LIST COLUMNS (id, n) (PARTITION p VALUES IN ((1, 2), (3, 4)))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        PartitioningRules::values(new ColumnsPartitioning(PartitionStrategy::List, ['id', 'n']), $alteration->partitioning->partitions);
        self::assertCount(1, $alteration->partitioning->partitions);
    }

    public function testSubpartitionsRejectsKeySubpartitioningWithoutColumns(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::subpartitions(new ColumnsPartitioning(PartitionStrategy::Range, ['id']), new KeyPartitioning([]), null, []);
    }

    public function testSubpartitionsAcceptsPartitionsWithoutSubpartitions(): void
    {
        $this->expectNotToPerformAssertions();
        PartitioningRules::subpartitions(new ColumnsPartitioning(PartitionStrategy::Range, ['id']), null, null, [new PartitionDefinition('p'), new PartitionDefinition('q')]);
    }

    public function testSubpartitionsRejectsSubpartitionsWithoutAFunction(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::subpartitions(new ColumnsPartitioning(PartitionStrategy::Range, ['id']), null, null, [new PartitionDefinition('p', null, subpartitions: [new SubpartitionDefinition('s')])]);
    }

    public function testSubpartitionsRejectsPartitionsWithDifferentSubpartitionCounts(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::subpartitions(new ColumnsPartitioning(PartitionStrategy::Range, ['id']), new KeyPartitioning(['n']), null, [
            new PartitionDefinition('p', null, subpartitions: [new SubpartitionDefinition('a'), new SubpartitionDefinition('b')]),
            new PartitionDefinition('q', null, subpartitions: [new SubpartitionDefinition('c')]),
        ]);
    }

    public function testSubpartitionsRejectsACountThatDisagreesWithTheDefinitions(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::subpartitions(new ColumnsPartitioning(PartitionStrategy::Range, ['id']), new KeyPartitioning(['n']), 3, [
            new PartitionDefinition('p', null, subpartitions: [new SubpartitionDefinition('a'), new SubpartitionDefinition('b')]),
        ]);
    }

    public function testSubpartitionsAcceptsEqualSubpartitionCounts(): void
    {
        $this->expectNotToPerformAssertions();
        PartitioningRules::subpartitions(new ColumnsPartitioning(PartitionStrategy::Range, ['id']), new KeyPartitioning(['n']), 2, [
            new PartitionDefinition('p', null, subpartitions: [new SubpartitionDefinition('a'), new SubpartitionDefinition('b')]),
            new PartitionDefinition('q', null, subpartitions: [new SubpartitionDefinition('c'), new SubpartitionDefinition('d')]),
        ]);
    }

    public function testSubpartitionsAcceptsEqualSubpartitionCountsWithoutACount(): void
    {
        $this->expectNotToPerformAssertions();
        PartitioningRules::subpartitions(new ColumnsPartitioning(PartitionStrategy::Range, ['id']), new KeyPartitioning(['n']), null, [
            new PartitionDefinition('p', null, subpartitions: [new SubpartitionDefinition('a')]),
            new PartitionDefinition('q', null, subpartitions: [new SubpartitionDefinition('b')]),
        ]);
    }

    public function testNamesRejectsASubpartitionNamedLikeAnotherInAnotherCase(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::names([new PartitionDefinition('p', null, subpartitions: [new SubpartitionDefinition('s'), new SubpartitionDefinition('S')])]);
    }

    public function testNamesRejectsASubpartitionNamedLikeAPartition(): void
    {
        $this->expectException(InvalidStructure::class);
        PartitioningRules::names([new PartitionDefinition('p', null, subpartitions: [new SubpartitionDefinition('P')])]);
    }

    public function testNamesAcceptsDistinctNames(): void
    {
        $this->expectNotToPerformAssertions();
        PartitioningRules::names([new PartitionDefinition('p', null, subpartitions: [new SubpartitionDefinition('s')]), new PartitionDefinition('q')]);
    }
}
