<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\TableRenaming;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableRenaming::class)]
#[Medium]
final class TableRenamingTest extends TestCase
{
    public function testNewNameRetainsTheDatabase(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('SELECT * FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $select);
        $table = $select->from;
        self::assertInstanceOf(TableReference::class, $table);
        $renaming = new TableRenaming($table, new QualifiedName(['archive', 't']));
        self::assertSame(['archive', 't'], $renaming->newName->parts);
        self::assertSame('t', $renaming->table->declaration->name);
    }

    public function testTableRejectsAQueryAlias(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('SELECT * FROM t AS a');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $select);
        $table = $select->from;
        self::assertInstanceOf(TableReference::class, $table);
        $this->expectException(InvalidStructure::class);
        new TableRenaming($table, new QualifiedName(['u']));
    }

    public function testNewNameRejectsThreeComponents(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('SELECT * FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $select);
        $table = $select->from;
        self::assertInstanceOf(TableReference::class, $table);
        $this->expectException(InvalidStructure::class);
        new TableRenaming($table, new QualifiedName(['a', 'b', 'c']));
    }
}
