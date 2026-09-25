<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\ExistsColumn;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ExistsColumn::class)]
#[Medium]
final class ExistsColumnTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'boolean'])]
    #[TestWith([Dialect::MySql, 'integer'])]
    public function testRetainsTheNameTypeAndPath(Dialect $dialect, string $type): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (ok " . $type . " EXISTS PATH '$.a')) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        $column = $statement->from->table->columns[0];
        self::assertInstanceOf(ExistsColumn::class, $column);
        self::assertSame('ok', $column->name);
        self::assertSame($type, $column->type->name);
        self::assertSame("'$.a'", $column->path?->spelling());
        self::assertSame(ExistsResponse::Default, $column->onError);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRetainsTheErrorResponse(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (ok BOOLEAN EXISTS PATH '$.a' UNKNOWN ON ERROR)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        $column = $statement->from->table->columns[0];
        self::assertInstanceOf(ExistsColumn::class, $column);
        self::assertSame(ExistsResponse::Unknown, $column->onError);
        self::assertSame('SELECT "j"."ok" AS "ok" FROM JSON_TABLE(\'[]\', \'$[*]\' COLUMNS("ok" boolean EXISTS PATH \'$.a\' UNKNOWN ON ERROR)) AS "j"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testLeavesThePathImplicitByDefault(): void
    {
        $column = new ExistsColumn('ok', TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'));
        self::assertNull($column->path);
        self::assertNull($column->collation);
        self::assertSame(ExistsResponse::Default, $column->onError);
    }
}
