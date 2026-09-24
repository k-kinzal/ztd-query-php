<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\MySqlTables;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Table\RenameTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlTables::class)]
#[Medium]
final class MySqlTablesTest extends TestCase
{
    public function testBindRoutesTableRenaming(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INT)')))->bind('RENAME TABLE a TO b');
        self::assertInstanceOf(RenameTablesStatement::class, $statement);
    }

    public function testBindLeavesOtherDialectsToTheirOwnForms(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE a(id INTEGER)')))->bind('ALTER TABLE a RENAME TO b');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\RenameTableStatement::class, $statement);
    }

    public function testBindRoutesTableAlteration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INT)')))->bind('ALTER TABLE a FORCE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement::class, $statement);
    }

    public function testBindRoutesTheMySql5ParserEntries(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('PARSE_GCOL_EXPR (1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\Table\GeneratedColumnExpressionStatement::class, $statement);
    }

    #[TestWith(['mysql-5.7.44', 'CREATE TABLE b(id INT)', \SqlSemantics\Model\Statement\CreateTableStatement::class, 'CREATE TABLE `b`(`id` integer)'])]
    #[TestWith(['mysql-5.7.44', 'TRUNCATE TABLE a', \SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement::class, 'TRUNCATE TABLE `a`'])]
    #[TestWith(['mysql-5.7.44', 'RENAME USER u TO v', \SqlSemantics\Model\Statement\Definition\MySql\Account\RenameUsersStatement::class, 'RENAME USER \'u\' TO \'v\''])]
    #[TestWith(['mysql-5.7.44', 'RENAME TABLE a TO b', RenameTablesStatement::class, 'RENAME TABLE `a` TO `b`'])]
    #[TestWith(['mysql-5.7.44', 'ANALYZE TABLE a', \SqlSemantics\Model\Statement\Maintenance\MySql\AnalyzeTablesStatement::class, 'ANALYZE TABLE `a`'])]
    #[TestWith(['mysql-5.7.44', 'LOCK TABLE a READ', \SqlSemantics\Model\Statement\Locking\LockTablesStatement::class, 'LOCK TABLES `a` READ'])]
    public function testBindRoutesOnlyTableAlterationAndRenaming(string $version, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE a(id INT)')))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, $statement->toString()]);
    }
}
