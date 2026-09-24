<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
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
}
