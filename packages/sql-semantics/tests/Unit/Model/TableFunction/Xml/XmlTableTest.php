<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Xml\Ordinality;
use SqlSemantics\Model\TableFunction\Xml\PassingMode;
use SqlSemantics\Model\TableFunction\Xml\XmlTable;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(XmlTable::class)]
#[Medium]
final class XmlTableTest extends TestCase
{
    public function testRetainsDocumentRowPathNamespacesAndModes(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT x.* FROM XMLTABLE (XMLNAMESPACES ('urn:x' AS x), '/x:rows/x:row' PASSING BY REF '<rows/>' BY VALUE COLUMNS n FOR ORDINALITY, value INTEGER PATH '@id') AS x");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        $table = $statement->from->table;
        self::assertInstanceOf(XmlTable::class, $table);
        self::assertSame(Dialect::PostgreSql, $table->dialect);
        self::assertSame("'<rows/>'", $table->document->spelling());
        self::assertSame("'/x:rows/x:row'", $table->rowPath->spelling());
        self::assertCount(1, $table->namespaces);
        self::assertCount(2, $table->columns);
        self::assertSame(PassingMode::Reference, $table->inputMode);
        self::assertSame(PassingMode::Value, $table->outputMode);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testDerivesTheDialectFromTheDocumentAndDefaultsTheOptions(): void
    {
        $table = new XmlTable(Expression::literal('<r/>', Dialect::PostgreSql), Expression::literal('/r', Dialect::PostgreSql), [new Ordinality('n')]);
        self::assertSame(Dialect::PostgreSql, $table->dialect);
        self::assertSame([], $table->namespaces);
        self::assertSame(PassingMode::Default, $table->inputMode);
        self::assertSame(PassingMode::Default, $table->outputMode);
    }

    public function testRequiresOutputDeclarations(): void
    {
        $this->expectException(InvalidStructure::class);
        new XmlTable(Expression::literal('<r/>', Dialect::PostgreSql), Expression::literal('/r', Dialect::PostgreSql), []);
    }

    public function testRejectsADialectWithoutXmlTable(): void
    {
        $this->expectException(InvalidStructure::class);
        new XmlTable(Expression::literal('<r/>', Dialect::MySql), Expression::literal('/r', Dialect::MySql), [new Ordinality('n')]);
    }
}
