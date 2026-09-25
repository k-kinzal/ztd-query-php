<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\ArrayWrapping;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\Quotes;
use SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse;
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ValueColumn::class)]
#[Medium]
final class ValueColumnTest extends TestCase
{
    public function testRetainsEveryDeclaredExtractionOption(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v JSONB FORMAT JSON PATH '$.b' WITH WRAPPER KEEP QUOTES DEFAULT '{}' ON EMPTY ERROR ON ERROR)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        $column = $statement->from->table->columns[0];
        self::assertInstanceOf(ValueColumn::class, $column);
        self::assertSame('v', $column->name);
        self::assertSame('jsonb', $column->type->name);
        self::assertSame("'$.b'", $column->path?->spelling());
        self::assertSame(Format::Json, $column->format);
        self::assertSame(ArrayWrapping::Unconditional, $column->wrapper);
        self::assertSame(Quotes::Keep, $column->quotes);
        self::assertInstanceOf(DefaultResponse::class, $column->onEmpty);
        self::assertSame("'{}'", $column->onEmpty->expression->spelling());
        self::assertSame(ValueBehavior::Error, $column->onError);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testDefaultsToAnImplicitPathWithoutOptions(): void
    {
        $column = new ValueColumn('v', TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'));
        self::assertNull($column->path);
        self::assertNull($column->collation);
        self::assertNull($column->format);
        self::assertSame(ArrayWrapping::Default, $column->wrapper);
        self::assertSame(Quotes::Default, $column->quotes);
        self::assertSame(ValueBehavior::Default, $column->onEmpty);
        self::assertSame(ValueBehavior::Default, $column->onError);
    }

    public function testBindsAnImplicitPathColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v INTEGER)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        self::assertInstanceOf(ValueColumn::class, $statement->from->table->columns[0]);
        self::assertNull($statement->from->table->columns[0]->path);
        self::assertSame('SELECT "j"."v" AS "v" FROM JSON_TABLE(\'[]\', \'$[*]\' COLUMNS("v" integer)) AS "j"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
