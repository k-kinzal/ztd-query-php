<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\NestedColumns;
use SqlSemantics\Model\TableFunction\Json\Response\TableError;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Document\JsonTables;

#[CoversClass(JsonTables::class)]
#[Medium]
final class JsonTablesTest extends TestCase
{
    public function testWriteSerializesPathsPassingArgumentsColumnsAndErrorPolicy(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT * FROM JSON_TABLE('[1]' FORMAT JSON, '$[*]' AS root PASSING 1 AS x COLUMNS (id FOR ORDINALITY, n INT PATH '$' WITH WRAPPER KEEP QUOTES DEFAULT 0 ON EMPTY ERROR ON ERROR, e INT EXISTS PATH '$.e' TRUE ON ERROR, t TEXT FORMAT JSON PATH '$' OMIT QUOTES, NESTED PATH '$.k[*]' AS nested COLUMNS (v TEXT PATH '$' NULL ON EMPTY)) ERROR ON ERROR) AS jt");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $table = $relation->table;
        self::assertInstanceOf(JsonTable::class, $table);
        $expected = "JSON_TABLE('[1]' FORMAT JSON, '$[*]' AS \"root\" PASSING 1 AS \"x\" COLUMNS(\"id\" FOR ORDINALITY, \"n\" integer PATH '$' WITH UNCONDITIONAL WRAPPER KEEP QUOTES DEFAULT 0 ON EMPTY ERROR ON ERROR, \"e\" integer EXISTS PATH '$.e' TRUE ON ERROR, \"t\" text FORMAT JSON PATH '$' OMIT QUOTES, NESTED PATH '$.k[*]' AS \"nested\" COLUMNS(\"v\" text PATH '$' NULL ON EMPTY)) ERROR ON ERROR)";
        self::assertSame($expected, JsonTables::write($table, Dialect::PostgreSql)->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(BoundSelect::class, $rebound);
        $again = $rebound->from;
        self::assertInstanceOf(DocumentRelation::class, $again);
        $reboundTable = $again->table;
        self::assertInstanceOf(JsonTable::class, $reboundTable);
        self::assertSame('root', $reboundTable->pathName);
        self::assertSame(TableError::Error, $reboundTable->onError);
        self::assertCount(5, $reboundTable->columns);
        self::assertSame($expected, JsonTables::write($reboundTable, Dialect::PostgreSql)->toString());
    }

    public function testWriteSerializesTheMysqlFormWithNestedColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS (id FOR ORDINALITY, n INT PATH '$' DEFAULT '0' ON EMPTY ERROR ON ERROR, e INT EXISTS PATH '$.e', NESTED PATH '$.k[*]' COLUMNS (v VARCHAR(10) PATH '$' NULL ON EMPTY))) AS jt");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expected = "SELECT `jt`.`id` AS `id`, `jt`.`n` AS `n`, `jt`.`e` AS `e`, `jt`.`v` AS `v` FROM JSON_TABLE('[1]', '$[*]' COLUMNS(`id` FOR ORDINALITY, `n` integer PATH '$' DEFAULT '0' ON EMPTY ERROR ON ERROR, `e` integer EXISTS PATH '$.e', NESTED PATH '$.k[*]' COLUMNS(`v` varchar(10) PATH '$' NULL ON EMPTY))) AS `jt`";
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testInputKeepsAnExplicitFormatWithItsExpression(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT * FROM JSON_TABLE('[1]' FORMAT JSON, '$[*]' COLUMNS (id FOR ORDINALITY)) AS jt");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $table = $relation->table;
        self::assertInstanceOf(JsonTable::class, $table);
        self::assertSame("'[1]' FORMAT JSON", JsonTables::input($table->document)->toString());
    }

    public function testColumnWritesEachColumnFormWithOnlyItsClauses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS (id FOR ORDINALITY, n INT PATH '$' DEFAULT 0 ON EMPTY, e INT EXISTS PATH '$.e', NESTED PATH '$.k[*]' COLUMNS (v TEXT PATH '$'))) AS jt");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $table = $relation->table;
        self::assertInstanceOf(JsonTable::class, $table);
        self::assertSame('"id" FOR ORDINALITY', JsonTables::column($table->columns[0], Dialect::PostgreSql)->toString());
        self::assertSame("\"n\" integer PATH '$' DEFAULT 0 ON EMPTY", JsonTables::column($table->columns[1], Dialect::PostgreSql)->toString());
        self::assertSame("\"e\" integer EXISTS PATH '$.e'", JsonTables::column($table->columns[2], Dialect::PostgreSql)->toString());
        self::assertInstanceOf(NestedColumns::class, $table->columns[3]);
        self::assertSame("NESTED PATH '$.k[*]' COLUMNS(\"v\" text PATH '$')", JsonTables::column($table->columns[3], Dialect::PostgreSql)->toString());
    }

    public function testResponseWritesASignedLiteralDefaultWithoutParentheses(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build());
        $statement = $binder->bind("SELECT * FROM JSON_TABLE('[]', '$[*]' COLUMNS (v INT PATH '$' DEFAULT -1 ON EMPTY)) j");
        self::assertSame("SELECT `j`.`v` AS `v` FROM JSON_TABLE('[]', '$[*]' COLUMNS(`v` integer PATH '$' DEFAULT - 1 ON EMPTY)) AS `j`", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testResponseWritesDefaultsAndBehaviorsAgainstTheirCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS (n INT PATH '$' DEFAULT 0 ON EMPTY ERROR ON ERROR, m INT PATH '$')) AS jt");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $table = $relation->table;
        self::assertInstanceOf(JsonTable::class, $table);
        $column = $table->columns[0];
        self::assertInstanceOf(ValueColumn::class, $column);
        self::assertSame('DEFAULT 0 ON EMPTY', JsonTables::response($column->onEmpty, 'EMPTY')->toString());
        self::assertSame('ERROR ON ERROR', JsonTables::response($column->onError, 'ERROR')->toString());
        $plain = $table->columns[1];
        self::assertInstanceOf(ValueColumn::class, $plain);
        self::assertSame('', JsonTables::response($plain->onEmpty, 'EMPTY')->toString());
    }
}
