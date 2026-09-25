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
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\Ordinality;
use SqlSemantics\Model\TableFunction\Json\Response\TableError;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonTable::class)]
#[Medium]
final class JsonTableTest extends TestCase
{
    public function testRetainsThePathNamePassingVariablesAndErrorResponse(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' AS root PASSING 2 AS k COLUMNS (n FOR ORDINALITY) ERROR ON ERROR) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        $table = $statement->from->table;
        self::assertInstanceOf(JsonTable::class, $table);
        self::assertSame(Dialect::PostgreSql, $table->dialect);
        self::assertSame('root', $table->pathName);
        self::assertSame("'$[*]'", $table->path->spelling());
        self::assertSame(['k'], array_column($table->passing, 'name'));
        self::assertSame(TableError::Error, $table->onError);
        self::assertCount(1, $table->columns);
        self::assertSame('SELECT "j"."n" AS "n" FROM JSON_TABLE(\'[]\', \'$[*]\' AS "root" PASSING 2 AS "k" COLUMNS("n" FOR ORDINALITY) ERROR ON ERROR) AS "j"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testDerivesTheDialectFromTheDocumentAndDefaultsTheOptions(): void
    {
        $table = new JsonTable(new Input(Expression::literal('{}', Dialect::MySql)), Expression::literal('$[*]', Dialect::MySql), [new Ordinality('n')]);
        self::assertSame(Dialect::MySql, $table->dialect);
        self::assertNull($table->pathName);
        self::assertSame([], $table->passing);
        self::assertSame(TableError::Default, $table->onError);
    }

    public function testRequiresOutputColumnDeclarations(): void
    {
        $this->expectException(InvalidStructure::class);
        new JsonTable(new Input(Expression::literal('{}', Dialect::PostgreSql)), Expression::literal('$[*]', Dialect::PostgreSql), []);
    }
}
