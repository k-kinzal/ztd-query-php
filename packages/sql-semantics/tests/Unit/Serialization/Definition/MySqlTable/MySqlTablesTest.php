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
}
