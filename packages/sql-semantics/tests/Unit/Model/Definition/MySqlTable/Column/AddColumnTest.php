<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column\AddColumn;
use SqlSemantics\Model\Definition\MySqlTable\Column\AfterColumn;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddColumn::class)]
#[Medium]
final class AddColumnTest extends TestCase
{
    public function testReadsTheColumnConstraintsAndPosition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD COLUMN c INT NOT NULL UNIQUE AFTER id');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddColumn::class, $alteration);
        self::assertSame('c', $alteration->column->name);
        self::assertCount(1, $alteration->constraints);
        self::assertInstanceOf(AfterColumn::class, $alteration->position);
        self::assertSame('id', $alteration->position->column);
    }

    public function testRejectsAnotherDialect(): void
    {
        $column = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')->tables[0]->columns[0];
        $this->expectException(InvalidStructure::class);
        new AddColumn($column);
    }
}
