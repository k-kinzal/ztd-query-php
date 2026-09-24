<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\TableFunction\RowsFrom\RowsFromRelation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Query\RowsFromTables;

#[CoversClass(RowsFromTables::class)]
#[Medium]
final class RowsFromTablesTest extends TestCase
{
    public function testWriteListsInvocationsAndOrdinality(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f(1), g()) WITH ORDINALITY');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        self::assertSame('ROWS FROM("f"(1), "g"()) WITH ORDINALITY', RowsFromTables::write($statement->from->table)->toString());
    }

    public function testFunctionWritesTheColumnDefinitionList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f() AS (a int, b varchar(3)))');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(RowsFromRelation::class, $statement->from);
        self::assertSame('"f"() AS ("a" integer, "b" varchar(3))', RowsFromTables::function($statement->from->table->functions[0])->toString());
    }
}
