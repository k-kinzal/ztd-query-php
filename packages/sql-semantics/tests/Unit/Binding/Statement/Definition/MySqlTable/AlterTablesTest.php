<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\AlterTables;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterTables::class)]
#[Medium]
final class AlterTablesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindKeepsATrailingPartitioningChangeLast(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('ALTER TABLE t ADD n INT, COMMENT = \'x\' REMOVE PARTITIONING');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame(TableCommand::RemovePartitioning, $statement->alterations[2]);
        self::assertSame('ALTER TABLE `t` ADD COLUMN `n` integer, COMMENT = \'x\' REMOVE PARTITIONING', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testBindDiagnosesInstantOnMySql57(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AlterAlgorithm->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALGORITHM = INSTANT');
    }

    public function testBindDiagnosesAnUnknownTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER TABLE missing ADD c INT', strict: false);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertFalse($statement->table->declaration->resolved);
    }

    public function testCommandsFindTheMySql56StandaloneCommand(): void
    {
        $statement = (new DialectParser(Dialect::MySql, 'mysql-5.6.51'))->parse('ALTER TABLE t DROP PARTITION p');
        self::assertSame(['alter_commands'], array_map(static fn ($command): string => $command->name, AlterTables::commands($statement)));
    }

    public function testCommandBindsAPartitionClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY HASH (id)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        self::assertNull($alteration->partitioning->partitionCount);
    }

    public function testScopeResolvesColumnsTheStatementAdds(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD c INT, ADD CHECK (c > 0)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame([], $statement->diagnostics);
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindKeepsEachTableCommand(): array
    {
        return [
            [Dialect::MySql, null, 'ALTER TABLE t EXCHANGE PARTITION p0 WITH TABLE u without validation', [AlterTableStatement::class, 'ALTER TABLE `t` WITHOUT VALIDATION, EXCHANGE PARTITION `p0` WITH TABLE `u`']],
            [Dialect::MySql, null, 'ALTER TABLE t with validation, EXCHANGE PARTITION p0 WITH TABLE u', [AlterTableStatement::class, 'ALTER TABLE `t` WITH VALIDATION, EXCHANGE PARTITION `p0` WITH TABLE `u`']],
            [Dialect::MySql, null, 'ALTER TABLE t WITH VALIDATION, EXCHANGE PARTITION p0 WITH TABLE u WITHOUT VALIDATION', [AlterTableStatement::class, 'ALTER TABLE `t` WITHOUT VALIDATION, EXCHANGE PARTITION `p0` WITH TABLE `u`']],
            [Dialect::MySql, null, 'ALTER TABLE t PARTITION BY HASH(a) PARTITIONS 2', [AlterTableStatement::class, 'ALTER TABLE `t` PARTITION BY HASH(`a`) PARTITIONS 2']],
            [Dialect::MySql, null, 'ALTER TABLE t REMOVE PARTITIONING', [AlterTableStatement::class, 'ALTER TABLE `t` REMOVE PARTITIONING']],
            [Dialect::MySql, null, 'ALTER TABLE t TRUNCATE PARTITION p0', [AlterTableStatement::class, 'ALTER TABLE `t` TRUNCATE PARTITION `p0`']],
            [Dialect::MySql, null, 'ALTER TABLE t ALGORITHM = INSTANT, ADD COLUMN b INT', [AlterTableStatement::class, 'ALTER TABLE `t` ALGORITHM = INSTANT, ADD COLUMN `b` integer']],
            [Dialect::MySql, 'mysql-5.6.51', 'ALTER TABLE t DROP PARTITION p0', [AlterTableStatement::class, 'ALTER TABLE `t` DROP PARTITION `p0`']],
            [Dialect::MySql, 'mysql-5.6.51', 'ALTER IGNORE TABLE t ADD COLUMN b INT', [AlterTableStatement::class, 'ALTER IGNORE TABLE `t` ADD COLUMN `b` integer']],
        ];
    }

    #[DataProvider('providerBindKeepsEachTableCommand')]
    public function testBindKeepsEachTableCommand(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT); CREATE TABLE u(a INT)')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
