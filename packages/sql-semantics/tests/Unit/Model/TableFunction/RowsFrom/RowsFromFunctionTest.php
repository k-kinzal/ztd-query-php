<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\RowsFrom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\TableFunction\RowsFrom\DefinedColumn;
use SqlSemantics\Model\TableFunction\RowsFrom\RowsFromFunction;
use SqlSemantics\Model\TableFunction\RowsFrom\RowsFromRelation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(RowsFromFunction::class)]
#[Medium]
final class RowsFromFunctionTest extends TestCase
{
    public function testKeepsTheCallAndColumnDefinitions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f(1) AS (a integer, b text))');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $function = $statement->from->table->functions[0];
        self::assertSame('F', $function->call->spelling());
        self::assertSame(['a', 'b'], array_map(static fn (DefinedColumn $column): string => $column->name, $function->columns));
    }

    public function testRejectsRepeatedDefinedColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f(1))');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        $type = TypeDescriptor::builtin(Dialect::PostgreSql, 'text');
        $this->expectException(InvalidStructure::class);
        new RowsFromFunction($statement->from->table->functions[0]->call, [new DefinedColumn('a', $type), new DefinedColumn('a', $type)]);
    }
}
