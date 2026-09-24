<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Document\XmlConstructorBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Document\Xml\XmlElement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(XmlConstructorBinder::class)]
#[Medium]
final class XmlConstructorBinderTest extends TestCase
{
    public function testElementReadsNameAttributesAndContent(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, x XML)'));
        $query = $binder->bind("SELECT XMLELEMENT(NAME \"Item\", XMLATTRIBUTES(t.n, 'k' AS n2), x) FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $element = $query->outputs[0]->expression;
        self::assertInstanceOf(XmlElement::class, $element);
        self::assertSame('Item', $element->name);
        self::assertSame(['n', 'n2'], [$element->attributes[0]->label(), $element->attributes[1]->label()]);
        self::assertSame('SELECT XMLELEMENT(NAME "Item", XMLATTRIBUTES("t"."n", \'k\' AS "n2"), "x") FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    #[TestWith(['SELECT XMLELEMENT(NAME e, XMLATTRIBUTES(t.n, n)) FROM t'])]
    #[TestWith(['SELECT XMLELEMENT(NAME e, XMLATTRIBUTES(1 AS b, 2 AS b))'])]
    #[TestWith(['SELECT XMLELEMENT(NAME e, XMLATTRIBUTES(n + 1)) FROM t'])]
    #[TestWith(['SELECT XMLFOREST(n + 1) FROM t'])]
    public function testElementRejectsAnUnnamedExpressionOrARepeatedAttribute(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::XmlValueName->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)')))->bind($sql);
    }

    public function testForestIsNullOnlyWhenEveryValueIs(): void
    {
        $forest = XmlConstructorBinder::forest((new DialectParser(Dialect::PostgreSql))->parse("SELECT XMLFOREST(NULL AS a, 'x' AS b)")->find('func_expr_common_subexpr')[0], new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame(Nullability::NotNull, $forest->nullability);
        self::assertSame(['a', 'b'], [$forest->elements[0]->alias, $forest->elements[1]->alias]);
    }

    #[TestWith(["SELECT XMLPI(NAME p, 'x')", Nullability::NotNull])]
    #[TestWith(['SELECT XMLPI(NAME p, NULL)', Nullability::AlwaysNull])]
    #[TestWith(['SELECT XMLPI(NAME p)', Nullability::NotNull])]
    public function testInstructionIsNullWhenItsContentIs(string $sql, Nullability $expected): void
    {
        $instruction = XmlConstructorBinder::instruction((new DialectParser(Dialect::PostgreSql))->parse($sql)->find('func_expr_common_subexpr')[0], new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame('p', $instruction->target);
        self::assertSame($expected, $instruction->nullability);
    }

    public function testConcatenationIsNullOnlyWhenEveryValueIs(): void
    {
        $concatenation = XmlConstructorBinder::concatenation((new DialectParser(Dialect::PostgreSql))->parse('SELECT XMLCONCAT(NULL, NULL)')->find('func_expr_common_subexpr')[0], new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertCount(2, $concatenation->values);
        self::assertSame(Nullability::AlwaysNull, $concatenation->nullability);
    }

    public function testNamedKeepsTheAlias(): void
    {
        $named = XmlConstructorBinder::named((new DialectParser(Dialect::PostgreSql))->parse('SELECT XMLFOREST(1 AS "One")')->find('xml_attribute_el')[0], new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame('One', $named->alias);
    }

    public function testLabelReadsTheName(): void
    {
        self::assertSame('php', XmlConstructorBinder::label((new DialectParser(Dialect::PostgreSql))->parse('SELECT XMLPI(NAME PHP)')->find('func_expr_common_subexpr')[0], new Scope(new Identifiers(Dialect::PostgreSql))));
    }

    public function testValuesReadsTheExpressionList(): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse("SELECT XMLELEMENT(NAME a, 'b', 'c')")->find('func_expr_common_subexpr')[0];
        self::assertSame(["'b'", "'c'"], array_map(static fn ($value): ?string => $value->spelling(), XmlConstructorBinder::values($source, new Scope(new Identifiers(Dialect::PostgreSql)))));
    }
}
