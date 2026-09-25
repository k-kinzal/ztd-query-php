<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\Document\JsonColumns;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonColumns::class)]
#[Medium]
final class JsonColumnsTest extends TestCase
{
    public function testBindKeepsEveryColumnKindInDeclarationOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.id FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (id INT PATH '$.id' DEFAULT '0' ON EMPTY ERROR ON ERROR, n FOR ORDINALITY, NESTED PATH '$.k[*]' COLUMNS (k VARCHAR(10) PATH '$'), e INT EXISTS PATH '$.e')) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        $columns = $relation->table->columns;
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $columns[0]);
        self::assertSame('id', $columns[0]->name);
        self::assertSame('integer', $columns[0]->type->name);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse::class, $columns[0]->onEmpty);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior::Error, $columns[0]->onError);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\Ordinality::class, $columns[1]);
        self::assertSame('n', $columns[1]->name);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\NestedColumns::class, $columns[2]);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ExistsColumn::class, $columns[3]);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse::Default, $columns[3]->onError);
        self::assertSame(['id', 'n', 'k', 'e'], array_column($relation->outputs, 'name'));
    }

    public function testColumnBindsNestedPathsWithTheirOwnColumnsAndName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.k FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (NESTED PATH '$.k[*]' AS nk COLUMNS (k TEXT PATH '$'))) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        $nested = $relation->table->columns[0];
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\NestedColumns::class, $nested);
        self::assertSame('nk', $nested->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $nested->path);
        self::assertSame("'$.k[*]'", $nested->path->text);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $nested->columns[0]);
        self::assertSame('k', $nested->columns[0]->name);
    }

    public function testColumnReadsExistsResponsesAndValueOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.e FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (e INT EXISTS PATH '$.e' TRUE ON ERROR, s TEXT FORMAT JSON PATH '$.s' WITH WRAPPER KEEP QUOTES, q TEXT PATH '$.q' OMIT QUOTES NULL ON EMPTY EMPTY ARRAY ON ERROR)) AS jt");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        [$exists, $wrapped, $quoted] = $relation->table->columns;
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ExistsColumn::class, $exists);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse::True, $exists->onError);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $wrapped);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Format::Json, $wrapped->format);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\ArrayWrapping::Unconditional, $wrapped->wrapper);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Quotes::Keep, $wrapped->quotes);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $quoted);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Quotes::Omit, $quoted->quotes);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior::Null, $quoted->onEmpty);
        self::assertSame(\SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior::EmptyArray, $quoted->onError);
    }

    public function testColumnReadsTheCollationAndLowerCaseExists(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind("SELECT * FROM JSON_TABLE('[]', '$[*]' COLUMNS (a varchar(9) collate utf8mb4_bin path '$.a', b int exists path '$.b'))");
        self::assertSame("SELECT `json_table`.`a` AS `a`, `json_table`.`b` AS `b` FROM JSON_TABLE('[]', '$[*]' COLUMNS(`a` varchar(9) COLLATE `utf8mb4_bin` PATH '$.a', `b` integer EXISTS PATH '$.b'))", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[0];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        [$value, $exists] = $relation->table->columns;
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $value);
        self::assertSame(['utf8mb4_bin'], $value->collation?->parts);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ExistsColumn::class, $exists);
        self::assertNull($exists->collation);
    }

    public function testColumnRejectsAnEmptyResponseOnAnExistsColumn(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::JsonOption->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind("SELECT * FROM JSON_TABLE('[]', '$[*]' COLUMNS (b int exists path '$.b' null on empty))");
    }

    public function testColumnBindsOneColumnDefinitionDirectly(): void
    {
        $source = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("SELECT * FROM JSON_TABLE('[]', '$[*]' COLUMNS (n FOR ORDINALITY))")->find('jt_column')[0];
        $column = JsonColumns::column($source, new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\Ordinality::class, $column);
        self::assertSame('n', $column->name);
    }
}
