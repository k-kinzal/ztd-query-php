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
use SqlSemantics\Model\TableFunction\Xml\PassingMode;
use SqlSemantics\Model\TableFunction\Xml\XmlTable;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Document\XmlTables;

#[CoversClass(XmlTables::class)]
#[Medium]
final class XmlTablesTest extends TestCase
{
    public function testWriteSerializesNamespacesPassingModesAndColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT * FROM XMLTABLE(XMLNAMESPACES('http://x' AS x, DEFAULT 'http://y'), '/rows/row' PASSING BY VALUE '<rows/>' BY REF COLUMNS id FOR ORDINALITY, n INT PATH 'n' DEFAULT 0 NOT NULL, t TEXT) AS xt");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $table = $relation->table;
        self::assertInstanceOf(XmlTable::class, $table);
        $expected = "XMLTABLE(XMLNAMESPACES('http://x' AS \"x\", DEFAULT 'http://y'), '/rows/row' PASSING BY VALUE '<rows/>' BY REF COLUMNS \"id\" FOR ORDINALITY, \"n\" integer PATH 'n' DEFAULT 0 NOT NULL, \"t\" text)";
        self::assertSame($expected, XmlTables::write($table, Dialect::PostgreSql)->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(BoundSelect::class, $rebound);
        $again = $rebound->from;
        self::assertInstanceOf(DocumentRelation::class, $again);
        $reboundTable = $again->table;
        self::assertInstanceOf(XmlTable::class, $reboundTable);
        self::assertSame(PassingMode::Value, $reboundTable->inputMode);
        self::assertSame(PassingMode::Reference, $reboundTable->outputMode);
        self::assertCount(2, $reboundTable->namespaces);
        self::assertSame($expected, XmlTables::write($reboundTable, Dialect::PostgreSql)->toString());
    }

    public function testWriteOmitsDefaultPassingModesAndNamespaces(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT * FROM XMLTABLE('/r' PASSING '<r/>' COLUMNS a TEXT PATH 'a') AS xt");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expected = "SELECT \"xt\".\"a\" AS \"a\" FROM XMLTABLE('/r' PASSING '<r/>' COLUMNS \"a\" text PATH 'a') AS \"xt\"";
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testNamespaceQuotesPrefixesIndependentlyOfUris(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT * FROM XMLTABLE(XMLNAMESPACES('http://x' AS x, DEFAULT 'http://y'), '/r' PASSING '<r/>' COLUMNS a TEXT) AS xt");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $table = $relation->table;
        self::assertInstanceOf(XmlTable::class, $table);
        self::assertSame("'http://x' AS \"x\"", XmlTables::namespace($table->namespaces[0], Dialect::PostgreSql)->toString());
        self::assertSame("DEFAULT 'http://y'", XmlTables::namespace($table->namespaces[1], Dialect::PostgreSql)->toString());
    }

    public function testColumnWritesOrdinalityWithoutPathTypeOrDefault(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT * FROM XMLTABLE('/r' PASSING '<r/>' COLUMNS id FOR ORDINALITY, n INT PATH 'n' DEFAULT 0 NOT NULL, t TEXT) AS xt");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $table = $relation->table;
        self::assertInstanceOf(XmlTable::class, $table);
        self::assertSame('"id" FOR ORDINALITY', XmlTables::column($table->columns[0], Dialect::PostgreSql)->toString());
        self::assertSame("\"n\" integer PATH 'n' DEFAULT 0 NOT NULL", XmlTables::column($table->columns[1], Dialect::PostgreSql)->toString());
        self::assertSame('"t" text', XmlTables::column($table->columns[2], Dialect::PostgreSql)->toString());
    }
}
