<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Document\XmlBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Document\Xml;
use SqlSemantics\Model\TableFunction\Xml\PassingMode;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(XmlBinder::class)]
#[Medium]
final class XmlBinderTest extends TestCase
{
    /**
     * @param class-string<\SqlSemantics\Model\Expression> $class
     */
    #[TestWith(["SELECT XMLEXISTS('//a' PASSING NULL)", Xml\XmlExistence::class, "SELECT XMLEXISTS('//a' PASSING NULL)"])]
    #[TestWith(["SELECT XMLPARSE(DOCUMENT '<a/>')", Xml\XmlParse::class, "SELECT XMLPARSE(DOCUMENT '<a/>')"])]
    #[TestWith(['SELECT XMLSERIALIZE(CONTENT NULL AS text)', Xml\XmlSerialization::class, 'SELECT XMLSERIALIZE(CONTENT NULL AS text)'])]
    #[TestWith(["SELECT XMLROOT(NULL, VERSION '1.1')", Xml\XmlRoot::class, "SELECT XMLROOT(NULL, VERSION '1.1')"])]
    #[TestWith(['SELECT XMLELEMENT(NAME a)', Xml\XmlElement::class, 'SELECT XMLELEMENT(NAME "a")'])]
    #[TestWith(['SELECT XMLFOREST(NULL AS x)', Xml\XmlForest::class, 'SELECT XMLFOREST(NULL AS "x")'])]
    #[TestWith(['SELECT XMLPI(NAME p)', Xml\XmlProcessingInstruction::class, 'SELECT XMLPI(NAME "p")'])]
    #[TestWith(['SELECT XMLCONCAT(NULL)', Xml\XmlConcatenation::class, 'SELECT XMLCONCAT(NULL)'])]
    public function testBindDispatchesEachXmlFunction(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf($class, $query->outputs[0]->expression);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testBindLeavesOtherFunctionsAndDialectsAlone(): void
    {
        $scope = new Scope(new Identifiers(Dialect::PostgreSql));
        self::assertNull(XmlBinder::bind((new DialectParser(Dialect::PostgreSql))->parse('SELECT JSON_SCALAR(1)')->find('func_expr_common_subexpr')[0], $scope));
        self::assertNull(XmlBinder::bind((new DialectParser(Dialect::MySql))->parse('SELECT 1')->find('simple_expr')[0], new Scope(new Identifiers(Dialect::MySql))));
    }

    #[TestWith(['SELECT x IS DOCUMENT FROM t', 'SELECT NULL IS DOCUMENT', false, 'SELECT ("x" IS DOCUMENT) FROM "public"."t"'])]
    #[TestWith(['SELECT 1 FROM t WHERE x IS NOT DOCUMENT', 'SELECT NULL IS NOT DOCUMENT', true, 'SELECT 1 FROM "public"."t" WHERE ("x" IS NOT DOCUMENT)'])]
    public function testDocumentReadsTheNegation(string $sql, string $constant, bool $negated, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (x XML)'));
        $query = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
        $tree = (new DialectParser(Dialect::PostgreSql))->parse($constant);
        $predicate = XmlBinder::document($tree->find('a_expr')[0], new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame($negated, $predicate?->negated);
        self::assertSame(Nullability::AlwaysNull, $predicate->nullability);
        self::assertNull(XmlBinder::document($tree->find('a_expr')[1], new Scope(new Identifiers(Dialect::PostgreSql))));
    }

    #[TestWith(["SELECT XMLEXISTS('//a' PASSING BY REF '<a/>')", PassingMode::Reference, PassingMode::Default])]
    #[TestWith(["SELECT XMLEXISTS('//a' PASSING '<a/>' BY VALUE)", PassingMode::Default, PassingMode::Value])]
    #[TestWith(["SELECT XMLEXISTS('//a' PASSING BY VALUE '<a/>' BY REF)", PassingMode::Value, PassingMode::Reference])]
    public function testExistsReadsThePassingModeOnEachSide(string $sql, PassingMode $input, PassingMode $output): void
    {
        $exists = XmlBinder::exists((new DialectParser(Dialect::PostgreSql))->parse($sql)->find('func_expr_common_subexpr')[0], new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame($input, $exists->inputMode);
        self::assertSame($output, $exists->outputMode);
        self::assertSame('boolean', $exists->type->name);
    }

    #[TestWith(["SELECT XMLPARSE(CONTENT 'a' PRESERVE WHITESPACE)", true])]
    #[TestWith(["SELECT XMLPARSE(CONTENT 'a' STRIP WHITESPACE)", false])]
    public function testParseReadsTheWhitespaceHandling(string $sql, bool $preserve): void
    {
        $parse = XmlBinder::parse((new DialectParser(Dialect::PostgreSql))->parse($sql)->find('func_expr_common_subexpr')[0], new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame($preserve, $parse->preserveWhitespace);
        self::assertSame(Xml\XmlOption::Content, $parse->option);
    }

    #[TestWith(['SELECT XMLSERIALIZE(DOCUMENT NULL AS varchar(3) INDENT)', 'varchar', true])]
    #[TestWith(['SELECT XMLSERIALIZE(DOCUMENT NULL AS app.label)', 'app.label', false])]
    public function testSerializeTakesTheTargetAsResultType(string $sql, string $type, bool $indent): void
    {
        $serialization = XmlBinder::serialize((new DialectParser(Dialect::PostgreSql))->parse($sql)->find('func_expr_common_subexpr')[0], new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame($type, $serialization->type->name);
        self::assertSame($indent, $serialization->indent);
        self::assertSame(Nullability::AlwaysNull, $serialization->nullability);
    }

    #[TestWith(['SELECT XMLSERIALIZE(CONTENT NULL AS integer)'])]
    #[TestWith(['SELECT XMLSERIALIZE(CONTENT NULL AS bytea)'])]
    #[TestWith(['SELECT XMLSERIALIZE(CONTENT NULL AS timestamp)'])]
    public function testSerializeRejectsATargetThatIsNotACharacterString(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::XmlSerializationTarget->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testRootReadsTheVersionAndStandalone(): void
    {
        $root = XmlBinder::root((new DialectParser(Dialect::PostgreSql))->parse('SELECT XMLROOT(NULL, VERSION NO VALUE, STANDALONE YES)')->find('func_expr_common_subexpr')[0], new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertNull($root->version);
        self::assertSame(Xml\XmlStandalone::Yes, $root->standalone);
    }

    public function testOptionReadsDocumentOrContent(): void
    {
        self::assertSame(Xml\XmlOption::Document, XmlBinder::option((new DialectParser(Dialect::PostgreSql))->parse("SELECT XMLPARSE(DOCUMENT '<a/>')")->find('func_expr_common_subexpr')[0]));
    }

    public function testFactsFollowTheOperandsNullExtension(): void
    {
        $facts = XmlBinder::facts('xml', [], Nullability::MaybeNull);
        self::assertSame('xml', $facts->type->name);
        self::assertSame(Nullability::MaybeNull, $facts->nullability);
        self::assertSame([], $facts->nullExtendedBy);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerLowerCaseForms(): array
    {
        return [
            ['select xmlparse(document s.x preserve whitespace) from t left join s on true', 'SELECT XMLPARSE(DOCUMENT "s"."x" PRESERVE WHITESPACE) FROM "public"."t" LEFT JOIN "public"."s" ON true'],
            ['select xmlserialize(content s.x as text indent) from t left join s on true', 'SELECT XMLSERIALIZE(CONTENT "s"."x" AS text INDENT) FROM "public"."t" LEFT JOIN "public"."s" ON true'],
            ['select xmlroot(s.x, version \'1.0\', standalone yes) from t left join s on true', 'SELECT XMLROOT("s"."x", VERSION \'1.0\', STANDALONE YES) FROM "public"."t" LEFT JOIN "public"."s" ON true'],
            ['select s.x is document from t left join s on true', 'SELECT ("s"."x" IS DOCUMENT) FROM "public"."t" LEFT JOIN "public"."s" ON true'],
            ['select s.x is not document from t left join s on true', 'SELECT ("s"."x" IS NOT DOCUMENT) FROM "public"."t" LEFT JOIN "public"."s" ON true'],
            ['select xmlexists(\'//a\' passing by value s.x) from t left join s on true', 'SELECT XMLEXISTS(\'//a\' PASSING BY VALUE "s"."x") FROM "public"."t" LEFT JOIN "public"."s" ON true'],
            ['select xmlexists(\'//a\' passing s.x by ref) from t left join s on true', 'SELECT XMLEXISTS(\'//a\' PASSING "s"."x" BY REF) FROM "public"."t" LEFT JOIN "public"."s" ON true'],
            ['select xmlroot(s.x, version no value, standalone no value) from t left join s on true', 'SELECT XMLROOT("s"."x", VERSION NO VALUE, STANDALONE NO VALUE) FROM "public"."t" LEFT JOIN "public"."s" ON true'],
        ];
    }

    #[DataProvider('providerLowerCaseForms')]
    public function testBindReadsLowerCaseFormsOverANullExtendedDocument(string $sql, string $expected): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE s(x xml)')))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame('maybe-null', $query->outputs[0]->expression->nullability->value);
        self::assertCount(1, $query->outputs[0]->expression->nullExtendedBy);
    }
}
