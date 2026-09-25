<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\NestedColumns;
use SqlSemantics\Model\TableFunction\Json\Ordinality;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NestedColumns::class)]
#[Medium]
final class NestedColumnsTest extends TestCase
{
    public function testRetainsTheNestedPathItsNameAndChildColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (NESTED PATH '$.c[*]' AS sub COLUMNS (child FOR ORDINALITY, v INTEGER PATH '$'))) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        $nested = $statement->from->table->columns[0];
        self::assertInstanceOf(NestedColumns::class, $nested);
        self::assertSame("'$.c[*]'", $nested->path->spelling());
        self::assertSame('sub', $nested->name);
        self::assertCount(2, $nested->columns);
        self::assertSame(['child', 'v'], array_column($statement->from->outputs, 'name'));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testMySqlNestedPathsAreUnnamed(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (NESTED PATH '$.c[*]' COLUMNS (child FOR ORDINALITY))) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        self::assertInstanceOf(NestedColumns::class, $statement->from->table->columns[0]);
        self::assertNull($statement->from->table->columns[0]->name);
    }

    public function testRequiresChildColumns(): void
    {
        $this->expectException(InvalidStructure::class);
        new NestedColumns(Expression::literal('$.c[*]', Dialect::PostgreSql), []);
    }

    public function testKeepsItsChildDeclarationsInOrder(): void
    {
        $nested = new NestedColumns(Expression::literal('$.c[*]', Dialect::PostgreSql), [new Ordinality('a'), new Ordinality('b')]);
        self::assertNull($nested->name);
        self::assertSame(['a', 'b'], array_map(static fn (Ordinality $column): string => $column->name, array_values(array_filter($nested->columns, static fn ($column): bool => $column instanceof Ordinality))));
    }
}
