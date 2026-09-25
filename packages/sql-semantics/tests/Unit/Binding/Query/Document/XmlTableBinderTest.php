<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\Document\XmlTableBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(XmlTableBinder::class)]
#[Medium]
final class XmlTableBinderTest extends TestCase
{
    public function testBindRetainsPassingModesNamespacesAndColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x XML)')))->bind("SELECT c.a FROM t, XMLTABLE(XMLNAMESPACES('http://x' AS x, DEFAULT 'http://d'), '/r' PASSING BY VALUE t.x BY REF COLUMNS a INT PATH 'a' DEFAULT 0 NOT NULL, o FOR ORDINALITY, b TEXT) AS c");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        $table = $relation->table;
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Xml\XmlTable::class, $table);
        self::assertSame(\SqlSemantics\Model\TableFunction\Xml\PassingMode::Value, $table->inputMode);
        self::assertSame(\SqlSemantics\Model\TableFunction\Xml\PassingMode::Reference, $table->outputMode);
        self::assertSame('x', $table->document->columnBinding()?->column->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $table->rowPath);
        self::assertSame("'/r'", $table->rowPath->text);
        self::assertCount(2, $table->namespaces);
        self::assertSame(['a', 'o', 'b'], array_column($relation->outputs, 'name'));
        self::assertSame('SELECT "c"."a" AS "a" FROM "public"."t" CROSS JOIN XMLTABLE(XMLNAMESPACES(\'http://x\' AS "x", DEFAULT \'http://d\'), \'/r\' PASSING BY VALUE "t"."x" BY REF COLUMNS "a" integer PATH \'a\' DEFAULT 0 NOT NULL, "o" FOR ORDINALITY, "b" text) AS "c"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testNamespaceLeavesThePrefixAbsentForTheDefaultNamespace(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x XML)')))->bind("SELECT c.a FROM t, XMLTABLE(XMLNAMESPACES('http://x' AS x, DEFAULT 'http://d'), '/r' PASSING t.x COLUMNS a INT PATH 'a') AS c");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Xml\XmlTable::class, $relation->table);
        [$prefixed, $default] = $relation->table->namespaces;
        self::assertSame('x', $prefixed->prefix);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $prefixed->uri);
        self::assertSame("'http://x'", $prefixed->uri->text);
        self::assertNull($default->prefix);
        self::assertSame(\SqlSemantics\Model\TableFunction\Xml\PassingMode::Default, $relation->table->inputMode);
    }

    public function testColumnReadsPathDefaultAndNullabilityOrOrdinality(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x XML)')))->bind("SELECT c.a FROM t, XMLTABLE('/r' PASSING t.x COLUMNS a INT PATH 'a' DEFAULT 0 NOT NULL, o FOR ORDINALITY, b TEXT) AS c");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Xml\XmlTable::class, $relation->table);
        [$value, $ordinal, $bare] = $relation->table->columns;
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Xml\ValueColumn::class, $value);
        self::assertSame('integer', $value->type->name);
        self::assertNotNull($value->path);
        self::assertNotNull($value->default);
        self::assertTrue($value->notNull);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Xml\Ordinality::class, $ordinal);
        self::assertSame('o', $ordinal->name);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Xml\ValueColumn::class, $bare);
        self::assertNull($bare->path);
        self::assertNull($bare->default);
        self::assertFalse($bare->notNull);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $relation->outputs[0]->expression->nullability);
    }

    public function testColumnRejectsARepeatedOption(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::XmlOption->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x XML)')))->bind("SELECT * FROM t, XMLTABLE('/r' PASSING t.x COLUMNS a INT PATH 'a' PATH 'b') AS c");
    }

    #[\PHPUnit\Framework\Attributes\TestWith(["SELECT * FROM t, XMLTABLE(XMLNAMESPACES('http://x' AS x, DEFAULT 'http://d'), '/r' PASSING d COLUMNS n FOR ORDINALITY, v text path 'v' default 'd' not null, w int null) AS x", 'SELECT "t"."a" AS "a", "t"."d" AS "d", "x"."n" AS "n", "x"."v" AS "v", "x"."w" AS "w" FROM "public"."t" CROSS JOIN XMLTABLE(XMLNAMESPACES(\'http://x\' AS "x", DEFAULT \'http://d\'), \'/r\' PASSING "d" COLUMNS "n" FOR ORDINALITY, "v" text PATH \'v\' DEFAULT \'d\' NOT NULL, "w" integer) AS "x"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(["select * from t, xmltable('/r' passing by ref d by value columns v text path 'v') as x", 'SELECT "t"."a" AS "a", "t"."d" AS "d", "x"."v" AS "v" FROM "public"."t" CROSS JOIN XMLTABLE(\'/r\' PASSING BY REF "d" BY VALUE COLUMNS "v" text PATH \'v\') AS "x"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(["SELECT * FROM t, XMLTABLE('/r' PASSING d COLUMNS v text NOT NULL DEFAULT 'x') AS x", 'SELECT "t"."a" AS "a", "t"."d" AS "d", "x"."v" AS "v" FROM "public"."t" CROSS JOIN XMLTABLE(\'/r\' PASSING "d" COLUMNS "v" text DEFAULT \'x\' NOT NULL) AS "x"'])]
    public function testBindSpellsNamespacesModesAndColumnOptions(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, d XML)')))->bind($sql)));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(["SELECT * FROM t, XMLTABLE('/r' PASSING d COLUMNS v text NULL NOT NULL) AS x"])]
    #[\PHPUnit\Framework\Attributes\TestWith(["SELECT * FROM t, XMLTABLE('/r' PASSING d COLUMNS v text PATH 'a' PATH 'b') AS x"])]
    public function testColumnRejectsRepeatedOptions(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, d XML)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage('The XML column option is unknown or specified more than once.');
        $binder->bind($sql);
    }
}
