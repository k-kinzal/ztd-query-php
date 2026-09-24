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
        self::assertSame('ALTER TABLE `t` REBUILD PARTITION `a`, `b`', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
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
}
