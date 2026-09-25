<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Definition\MySqlTable\PartitionValidation;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterTableStatement::class)]
#[Medium]
final class AlterTableStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testAlterationsBindOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('ALTER TABLE t ADD COLUMN c INT AFTER id, DROP COLUMN n, ENGINE = InnoDB');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame(StatementKind::Alter, $statement->kind);
        self::assertCount(3, $statement->alterations);
        self::assertSame('ALTER TABLE `t` ADD COLUMN `c` integer AFTER `id`, DROP COLUMN `n`, ENGINE `InnoDB`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testIgnoreBindsOnMySql56(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(id INT)')))->bind('ALTER IGNORE TABLE t ALGORITHM = COPY, FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertTrue($statement->ignore);
        self::assertSame('ALTER IGNORE TABLE `t` ALGORITHM = COPY, FORCE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testAnEmptyListRequestsNoChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame([], $statement->alterations);
        self::assertSame('ALTER TABLE `t`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->alterations, $copy->alterations);
    }

    public function testWithTableReplacesTheTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)')))->bind('ALTER TABLE t FORCE');
        $other = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)')))->bind('SELECT * FROM u');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $other);
        self::assertInstanceOf(TableReference::class, $other->from);
        $changed = $statement->withTable($other->from);
        self::assertSame('ALTER TABLE `u` FORCE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('t', $statement->table->declaration->name);
    }

    public function testWithAlterationsReplacesTheAlterations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $changed = $statement->withAlterations([TableCommand::DisableKeys]);
        self::assertSame('ALTER TABLE `t` DISABLE KEYS', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame([TableCommand::Force], $statement->alterations);
    }

    public function testWithAlterationsRejectsACombinedStandaloneCommand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withAlterations([TableCommand::Force, TableCommand::ImportTablespace]);
    }

    public function testWithAlgorithmReplacesTheAlgorithm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame('ALTER TABLE `t` ALGORITHM = COPY, FORCE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withAlgorithm(TableAlgorithm::Copy)));
        self::assertSame(TableAlgorithm::Default, $statement->algorithm);
    }

    public function testWithAlgorithmRejectsInstantOnMySql57(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t(id INT)')))->bind('ALTER TABLE t FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withAlgorithm(TableAlgorithm::Instant);
    }

    public function testWithLockReplacesTheLock(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame('ALTER TABLE `t` LOCK = SHARED, FORCE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withLock(IndexLock::Shared)));
    }

    public function testWithValidationReplacesTheValidation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame('ALTER TABLE `t` WITHOUT VALIDATION, FORCE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withValidation(PartitionValidation::Without)));
    }

    public function testWithValidationRejectsMySql56(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(id INT)')))->bind('ALTER TABLE t FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withValidation(PartitionValidation::With);
    }
}
