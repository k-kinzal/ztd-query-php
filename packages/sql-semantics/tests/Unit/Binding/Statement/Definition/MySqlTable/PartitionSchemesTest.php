<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\PartitionSchemes;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Partition\ColumnsPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Partition\PartitionStrategy;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionSchemes::class)]
#[Medium]
final class PartitionSchemesTest extends TestCase
{
    public function testReadReturnsNullWithoutPartitioning(): void
    {
        self::assertNull(PartitionSchemes::read((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t FORCE'), new Scope(new Identifiers(Dialect::MySql))));
    }

    public function testBindDiagnosesAMissingDefinitionList(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PartitionDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY LIST (id)');
    }

    public function testFunctionDiagnosesAnUnknownKeyAlgorithm(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PartitionDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY KEY ALGORITHM = 3 (id)');
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testFunctionReadsColumnsOnEveryRelease(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER TABLE t PARTITION BY RANGE COLUMNS (a, b) (PARTITION p VALUES LESS THAN (1, 2))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        self::assertInstanceOf(ColumnsPartitioning::class, $alteration->partitioning->function);
        self::assertSame(['a', 'b'], $alteration->partitioning->function->columns);
    }

    public function testStrategyReadsRangeOrList(): void
    {
        self::assertSame(PartitionStrategy::List, PartitionSchemes::strategy(['LIST', 'COLUMNS']));
    }

    #[TestWith(['ALTER TABLE t PARTITION BY HASH(id) PARTITIONS 1.5'])]
    #[TestWith(['ALTER TABLE t PARTITION BY RANGE (id) SUBPARTITION BY HASH(id) SUBPARTITIONS 1.5 (PARTITION p VALUES LESS THAN (10))'])]
    #[TestWith(['ALTER TABLE t PARTITION BY KEY ALGORITHM = 1.5 (id)'])]
    public function testBindRejectsFractionalCounts(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PartitionDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind($sql);
    }

    #[TestWith(['ALTER TABLE t partition by linear hash(id) partitions 4', 'ALTER TABLE `t` PARTITION BY LINEAR HASH(`id`) PARTITIONS 4'])]
    #[TestWith(['ALTER TABLE t PARTITION BY RANGE (id) SUBPARTITION BY KEY(id) SUBPARTITIONS 2 (PARTITION p VALUES LESS THAN (10))', 'ALTER TABLE `t` PARTITION BY RANGE(`id`) SUBPARTITION BY KEY(`id`) SUBPARTITIONS 2(PARTITION `p` VALUES LESS THAN(10))'])]
    #[TestWith(['ALTER TABLE t PARTITION BY LIST (id) SUBPARTITION BY HASH(n) SUBPARTITIONS 3 (PARTITION p VALUES IN (1))', 'ALTER TABLE `t` PARTITION BY LIST(`id`) SUBPARTITION BY HASH(`n`) SUBPARTITIONS 3(PARTITION `p` VALUES IN(1))'])]
    #[TestWith(['ALTER TABLE t partition by linear key algorithm = 2 (id, n) partitions 2', 'ALTER TABLE `t` PARTITION BY LINEAR KEY ALGORITHM = 2(`id`, `n`) PARTITIONS 2'])]
    #[TestWith(['ALTER TABLE t PARTITION BY LIST COLUMNS (id) (PARTITION p VALUES IN (1))', 'ALTER TABLE `t` PARTITION BY LIST COLUMNS(`id`)(PARTITION `p` VALUES IN(1))'])]
    public function testBindReadsFunctionsAndCounts(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)'));
        self::assertSame($expected, $binder->bind($sql)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testBindReadsTheSubpartitioningAndItsCount(): void
    {
        $clause = \SqlSemantics\Ast\Tree::outer((new DialectParser(Dialect::MySql))->parse('ALTER TABLE t PARTITION BY KEY(id) PARTITIONS 4'), ['partition_clause', 'partition'])[0];
        $partitioning = PartitionSchemes::bind($clause, new Scope(new Identifiers(Dialect::MySql)));
        self::assertSame(4, $partitioning->partitionCount);
        self::assertNull($partitioning->subpartitioning);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\MySqlTable\Partition\KeyPartitioning::class, $partitioning->function);
        self::assertSame(['id'], $partitioning->function->columns);
    }

    public function testFunctionReadsLinearKeyPartitioning(): void
    {
        $type = \SqlSemantics\Ast\Tree::outer((new DialectParser(Dialect::MySql))->parse('ALTER TABLE t PARTITION BY linear key (a, b)'), ['part_type_def'])[0];
        $function = PartitionSchemes::function($type, new Scope(new Identifiers(Dialect::MySql)));
        self::assertInstanceOf(\SqlSemantics\Model\Definition\MySqlTable\Partition\KeyPartitioning::class, $function);
        self::assertTrue($function->linear);
        self::assertSame(['a', 'b'], $function->columns);
    }
}
