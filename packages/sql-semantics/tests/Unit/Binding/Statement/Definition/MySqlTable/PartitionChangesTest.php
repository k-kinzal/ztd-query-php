<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\PartitionChanges;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\ProcessPartitions;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\RebuildPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\SecondaryLoad;
use SqlSemantics\Model\Maintenance\IndexCache\AllPartitions;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionChanges::class)]
#[Medium]
final class PartitionChangesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsStandaloneCommandsOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('ALTER TABLE t REBUILD PARTITION a, b');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertInstanceOf(ProcessPartitions::class, $statement->alterations[0]);
        self::assertSame('ALTER TABLE `t` REBUILD PARTITION `a`, `b`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testAddDiagnosesAPartitionWithoutDefinitionsOrCount(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PartitionDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD PARTITION');
    }

    public function testReorganizeWithoutAListRebuildsThePartitioning(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t REORGANIZE PARTITION');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RebuildPartitioning::class, $alteration);
        self::assertSame(BinlogPolicy::Write, $alteration->binlog);
    }

    public function testExchangeDiagnosesAnUnknownTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE missing', strict: false);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame('unknown-table', $statement->diagnostics[0]->reason);
    }

    public function testSecondaryReadsThePartitionList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t SECONDARY_LOAD PARTITION (p)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(SecondaryLoad::class, $alteration);
        self::assertSame(['p'], $alteration->partitions);
    }

    public function testSelectionReadsAll(): void
    {
        self::assertSame(AllPartitions::All, PartitionChanges::selection((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t TRUNCATE PARTITION ALL')->find('standalone_alter_commands')[0], new Scope(new Identifiers(Dialect::MySql))));
    }

    public function testNamesSkipsTheExchangedTable(): void
    {
        self::assertSame(['p'], PartitionChanges::names((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u')->find('standalone_alter_commands')[0], new Scope(new Identifiers(Dialect::MySql))));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerBindSpellsEveryStandaloneCommand(): iterable
    {
        yield 'discard tablespace' => ['alter table t discard tablespace', 'ALTER TABLE `t` DISCARD TABLESPACE'];
        yield 'import tablespace' => ['alter table t import tablespace', 'ALTER TABLE `t` IMPORT TABLESPACE'];
        yield 'discard partition' => ['alter table t discard partition p tablespace', 'ALTER TABLE `t` DISCARD PARTITION `p` TABLESPACE'];
        yield 'import partition' => ['alter table t import partition all tablespace', 'ALTER TABLE `t` IMPORT PARTITION ALL TABLESPACE'];
        yield 'drop partition' => ['alter table t drop partition p, q', 'ALTER TABLE `t` DROP PARTITION `p`, `q`'];
        yield 'optimize partition' => ['alter table t optimize partition no_write_to_binlog p', 'ALTER TABLE `t` OPTIMIZE PARTITION NO_WRITE_TO_BINLOG `p`'];
        yield 'analyze partition' => ['alter table t analyze partition all', 'ALTER TABLE `t` ANALYZE PARTITION ALL'];
        yield 'check partition' => ['alter table t check partition p quick changed', 'ALTER TABLE `t` CHECK PARTITION `p` QUICK CHANGED'];
        yield 'repair partition' => ['alter table t repair partition p quick extended use_frm', 'ALTER TABLE `t` REPAIR PARTITION `p` QUICK EXTENDED USE_FRM'];
        yield 'coalesce partition' => ['alter table t coalesce partition local 2', 'ALTER TABLE `t` COALESCE PARTITION NO_WRITE_TO_BINLOG 2'];
        yield 'truncate partition' => ['alter table t truncate partition p', 'ALTER TABLE `t` TRUNCATE PARTITION `p`'];
        yield 'add partition count' => ['alter table t add partition no_write_to_binlog partitions 3', 'ALTER TABLE `t` ADD PARTITION NO_WRITE_TO_BINLOG PARTITIONS 3'];
        yield 'add partition definitions' => ['alter table t add partition (partition p3 values less than (10))', 'ALTER TABLE `t` ADD PARTITION(PARTITION `p3` VALUES LESS THAN(10))'];
        yield 'exchange partition' => ['alter table t exchange partition p with table u', 'ALTER TABLE `t` EXCHANGE PARTITION `p` WITH TABLE `u`'];
        yield 'secondary unload' => ['alter table t secondary_unload partition (p)', 'ALTER TABLE `t` SECONDARY_UNLOAD PARTITION(`p`)'];
    }

    #[DataProvider('providerBindSpellsEveryStandaloneCommand')]
    public function testBindSpellsEveryStandaloneCommand(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT); CREATE TABLE u(id INT, n INT)')))->bind($sql)));
    }

    public function testAddRejectsAZeroPartitionCount(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD PARTITION PARTITIONS 0');
    }

    public function testBindRejectsAZeroCoalesceCount(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t COALESCE PARTITION 0');
    }

    public function testSelectionReadsALowercaseAll(): void
    {
        self::assertSame(AllPartitions::All, PartitionChanges::selection((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t TRUNCATE PARTITION all')->find('standalone_alter_commands')[0], new Scope(new Identifiers(Dialect::MySql))));
    }
}
