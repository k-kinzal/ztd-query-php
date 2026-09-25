<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\Document\JsonTableBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonTableBinder::class)]
#[Medium]
final class JsonTableBinderTest extends TestCase
{
    public function testBindRetainsDocumentRowPathNameAndErrorPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.id FROM t, JSON_TABLE(t.doc, '$[*]' AS root COLUMNS (id INT PATH '$.id') ERROR ON ERROR) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        $table = $relation->table;
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $table);
        self::assertSame(Dialect::PostgreSql, $table->dialect);
        self::assertSame('doc', $table->document->expression->columnBinding()?->column->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $table->path);
        self::assertSame("'$[*]'", $table->path->text);
        self::assertSame('root', $table->pathName);
        self::assertSame([], $table->passing);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\TableError::Error, $table->onError);
        self::assertSame('SELECT "jt"."id" AS "id" FROM "public"."t" CROSS JOIN JSON_TABLE("t"."doc", \'$[*]\' AS "root" COLUMNS("id" integer PATH \'$.id\') ERROR ON ERROR) AS "jt"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindHandlesMySqlWithoutPathNameOrPassing(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.id FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (id INT PATH '$.id')) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        self::assertNull($relation->table->pathName);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\TableError::Default, $relation->table->onError);
        self::assertSame('integer', $statement->outputs[0]->expression->type->name);
    }

    public function testArgumentBindsEachPassingVariableWithItsFormat(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON, n INT)')))->bind("SELECT jt.id FROM t, JSON_TABLE(t.doc, '$[*]' PASSING t.n AS p, '{}' FORMAT JSON AS q COLUMNS (id INT PATH '$.id')) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        self::assertSame(['p', 'q'], array_column($relation->table->passing, 'name'));
        self::assertSame('n', $relation->table->passing[0]->input->expression->columnBinding()?->column->name);
        self::assertNull($relation->table->passing[0]->input->format);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Format::Json, $relation->table->passing[1]->input->format);
    }
}
