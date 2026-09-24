<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\ColumnSymbol;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ColumnSymbol::class)]
#[Medium]
final class ColumnSymbolTest extends TestCase
{
    public function testDescribesTheDeclaredColumnWithoutAValue(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $query = (new Binder($schema))->bind('SELECT x, id FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $binding = $query->outputs[0]->expression->columnBinding();
        self::assertNotNull($binding);
        self::assertSame(1, $binding->column->ordinal);
        self::assertSame('x', $binding->column->name);
        self::assertSame(Nullability::MaybeNull, $binding->column->nullability);
        self::assertSame($schema->tables[0]->columns[1]->type, $binding->column->type);
        self::assertSame(0, $query->outputs[1]->expression->columnBinding()?->column->ordinal);
    }

    public function testRejectsANegativePosition(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)');
        $column = $schema->tables[0]->columns[0];
        $this->expectException(InvalidStructure::class);
        new ColumnSymbol(-1, $column->name, $column->type, $column->nullability);
    }
}
