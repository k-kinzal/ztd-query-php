<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Table\RenameTablesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\MySqlTable\MySqlTables;

#[CoversClass(MySqlTables::class)]
#[Medium]
final class MySqlTablesTest extends TestCase
{
    public function testWriteWritesEveryRenamingPair(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INT)', 'CREATE TABLE b(id INT)')))->bind('RENAME TABLES a TO c, b TO d');
        self::assertSame('RENAME TABLE `a` TO `c`, `b` TO `d`', MySqlTables::write($statement)?->toString());
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertNull(MySqlTables::write($statement));
    }

    public function testRenamingWritesTheSourceAndTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INT)')))->bind('RENAME TABLE a TO app.c');
        self::assertInstanceOf(RenameTablesStatement::class, $statement);
        self::assertSame('`a` TO `app`.`c`', MySqlTables::renaming($statement->renamings[0])->toString());
    }

    public function testAlterWritesRequestsBeforeAlterations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('ALTER TABLE t ADD n INT, LOCK = NONE, ALGORITHM = INPLACE PARTITION BY KEY (id)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement::class, $statement);
        self::assertSame('ALTER TABLE `t` ALGORITHM = INPLACE, LOCK = NONE, ADD COLUMN `n` integer PARTITION BY KEY(`id`)', MySqlTables::alter($statement)->toString());
    }

    public function testAlterationWritesOneAlteration(): void
    {
        self::assertSame('FORCE', MySqlTables::alteration(\SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand::Force)->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7', 'ALTER TABLE t WITHOUT VALIDATION, ADD m INT', 'ALTER TABLE `t` WITHOUT VALIDATION, ADD COLUMN `m` integer'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7', 'ALTER TABLE t ALGORITHM = COPY', 'ALTER TABLE `t` ALGORITHM = COPY'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7', 'ALTER TABLE t LOCK = SHARED', 'ALTER TABLE `t` LOCK = SHARED'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7', 'ALTER TABLE t REMOVE PARTITIONING', 'ALTER TABLE `t` REMOVE PARTITIONING'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7', 'ALTER TABLE t ADD m INT, ADD o INT', 'ALTER TABLE `t` ADD COLUMN `m` integer, ADD COLUMN `o` integer'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7', 'ALTER TABLE t', 'ALTER TABLE `t`'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51', 'ALTER IGNORE TABLE t ADD m INT', 'ALTER IGNORE TABLE `t` ADD COLUMN `m` integer'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51', 'PARTITION BY KEY (id) PARTITIONS 2', 'PARTITION BY KEY(`id`) PARTITIONS 2'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44', 'PARSE_GCOL_EXPR (1 + 2)', 'PARSE_GCOL_EXPR((1 + 2))'])]
    public function testWriteSpellsEachTableForm(string $version, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT, n INT)')))->bind($sql);
        self::assertSame($expected, MySqlTables::write($statement)?->toString());
    }

    public function testFromQueryWritesTheDeclarationThenTheDuplicatePolicyAndTheQuery(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE u(a INT)')))->bind('create temporary table t (c int) comment = \'x\' ignore as select a from u');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\Table\CreateTableFromQueryStatement::class, $statement);
        self::assertSame("CREATE TEMPORARY TABLE `t`(`c` integer) COMMENT = 'x' IGNORE AS SELECT `a` AS `a` FROM `u`", MySqlTables::fromQuery($statement)->toString());
        self::assertSame(MySqlTables::fromQuery($statement)->toString(), MySqlTables::write($statement)?->toString());
    }

    public function testFromQueryOmitsAnAbsentDuplicatePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('CREATE TABLE t (c INT) SELECT 1 AS c');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\Table\CreateTableFromQueryStatement::class, $statement);
        self::assertSame('CREATE TABLE `t`(`c` integer) AS SELECT 1 AS `c`', MySqlTables::fromQuery($statement)->toString());
    }
}
