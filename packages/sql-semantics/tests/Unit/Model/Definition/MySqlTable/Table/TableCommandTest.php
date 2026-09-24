<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableCommand::class)]
#[Medium]
final class TableCommandTest extends TestCase
{
    public function testReadsOperandFreeAlterations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ENABLE KEYS, DROP PRIMARY KEY');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame([TableCommand::EnableKeys, TableCommand::DropPrimaryKey], $statement->alterations);
    }

    public function testReadsAStandaloneTablespaceCommand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t DISCARD TABLESPACE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame([TableCommand::DiscardTablespace], $statement->alterations);
    }
}
