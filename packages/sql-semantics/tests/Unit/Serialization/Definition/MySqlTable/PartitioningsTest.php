<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\ColumnsPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionDefinition;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionProperties;
use SqlSemantics\Model\Definition\MySqlTable\Partition\SubpartitionDefinition;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Schema\Partition\PartitionStrategy;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\MySqlTable\Partitionings;

#[CoversClass(Partitionings::class)]
#[Medium]
final class PartitioningsTest extends TestCase
{
    public function testWriteWritesTheWholeClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY RANGE (id) SUBPARTITION BY LINEAR KEY ALGORITHM = 1 (id) SUBPARTITIONS 2 (PARTITION p VALUES LESS THAN MAXVALUE)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        self::assertSame('PARTITION BY RANGE(`id`) SUBPARTITION BY LINEAR KEY ALGORITHM = 1(`id`) SUBPARTITIONS 2(PARTITION `p` VALUES LESS THAN MAXVALUE)', Partitionings::write($alteration->partitioning)->toString());
    }

    public function testFunctionWritesColumns(): void
    {
        self::assertSame('LIST COLUMNS(`a`)', Partitionings::function(new ColumnsPartitioning(PartitionStrategy::List, ['a']))->toString());
    }

    public function testDefinitionsWriteEachPartition(): void
    {
        self::assertSame('(PARTITION `a`, PARTITION `b`)', Partitionings::definitions([new PartitionDefinition('a'), new PartitionDefinition('b')])->toString());
    }

    public function testDefinitionWritesSubpartitions(): void
    {
        self::assertSame('PARTITION `a`(SUBPARTITION `s` COMMENT = \'x\')', Partitionings::definition(new PartitionDefinition('a', null, new PartitionProperties(), [new SubpartitionDefinition('s', new PartitionProperties(comment: 'x'))]))->toString());
    }

    public function testValuesWritesTuples(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY LIST COLUMNS (id, n) (PARTITION p VALUES IN ((1, 2)))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $values = $alteration->partitioning->partitions[0]->values;
        self::assertNotNull($values);
        self::assertSame('VALUES IN((1, 2))', Partitionings::values($values)->toString());
    }

    public function testPropertiesWritesEveryOption(): void
    {
        self::assertSame('ENGINE = `e` TABLESPACE = `s` COMMENT = \'c\' MAX_ROWS = 1 NODEGROUP = 0', Partitionings::properties(new PartitionProperties('e', 'c', null, null, 1, null, 's', 0))->toString());
    }

    public function testColumnsQuotesNames(): void
    {
        self::assertSame('(`a`, `b`)', Partitionings::columns(['a', 'b'])->toString());
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerWriteSpellsEachPartitioningForm(): array
    {
        return [
            [Dialect::MySql, null, 'CREATE TABLE p (a INT, b INT) PARTITION BY LINEAR HASH(a) PARTITIONS 4', [\SqlSemantics\Model\Statement\CreateTableStatement::class, 'CREATE TABLE `p`(`a` integer, `b` integer) PARTITION BY LINEAR HASH(`a`) PARTITIONS 4']],
            [Dialect::MySql, null, 'CREATE TABLE p (a INT, b INT) PARTITION BY HASH(a)', [\SqlSemantics\Model\Statement\CreateTableStatement::class, 'CREATE TABLE `p`(`a` integer, `b` integer) PARTITION BY HASH(`a`)']],
            [Dialect::MySql, null, 'CREATE TABLE p (a INT, b INT) PARTITION BY RANGE (a) SUBPARTITION BY HASH (b) SUBPARTITIONS 2 (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN MAXVALUE)', [\SqlSemantics\Model\Statement\CreateTableStatement::class, 'CREATE TABLE `p`(`a` integer, `b` integer) PARTITION BY RANGE(`a`) SUBPARTITION BY HASH(`b`) SUBPARTITIONS 2(PARTITION `p0` VALUES LESS THAN(10), PARTITION `p1` VALUES LESS THAN MAXVALUE)']],
            [Dialect::MySql, null, 'CREATE TABLE p (a INT, b INT) PARTITION BY LIST (a) (PARTITION p0 VALUES IN (1, 2), PARTITION p1 VALUES IN (3))', [\SqlSemantics\Model\Statement\CreateTableStatement::class, 'CREATE TABLE `p`(`a` integer, `b` integer) PARTITION BY LIST(`a`)(PARTITION `p0` VALUES IN(1, 2), PARTITION `p1` VALUES IN(3))']],
            [Dialect::MySql, null, 'CREATE TABLE p (a INT, b INT) PARTITION BY LIST COLUMNS (a, b) (PARTITION p0 VALUES IN ((1, 2), (3, 4)))', [\SqlSemantics\Model\Statement\CreateTableStatement::class, 'CREATE TABLE `p`(`a` integer, `b` integer) PARTITION BY LIST COLUMNS(`a`, `b`)(PARTITION `p0` VALUES IN((1, 2), (3, 4)))']],
            [Dialect::MySql, null, 'CREATE TABLE p (a INT, b INT) PARTITION BY KEY (a) PARTITIONS 3', [\SqlSemantics\Model\Statement\CreateTableStatement::class, 'CREATE TABLE `p`(`a` integer, `b` integer) PARTITION BY KEY(`a`) PARTITIONS 3']],
        ];
    }

    #[DataProvider('providerWriteSpellsEachPartitioningForm')]
    public function testWriteSpellsEachPartitioningForm(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build()))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
