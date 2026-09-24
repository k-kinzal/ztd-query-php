<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Scalar\Operator\QuantifiedOperator;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Conditional\PatternOperator;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator;
use SqlSemantics\Model\Scalar\Query\ComparisonOperator;
use SqlSemantics\Model\Validation\InputViolation;

#[CoversClass(QuantifiedOperator::class)]
#[Medium]
final class QuantifiedOperatorTest extends TestCase
{
    #[TestWith(['SELECT 1 OPERATOR(pg_catalog.<) ALL (SELECT 1)', ComparisonOperator::Less, false])]
    #[TestWith(["SELECT 'a' NOT ILIKE ANY (SELECT 'b')", PatternOperator::ILike, true])]
    #[TestWith(['SELECT 1 != ANY (SELECT 1)', null, false])]
    public function testSubqueryClassifiesNamedAndPatternOperators(string $sql, ComparisonOperator|PatternOperator|null $expected, bool $negated): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse($sql)->find('a_expr')[0];
        $choice = QuantifiedOperator::subquery($source, new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame($expected, $choice?->operator);
        self::assertSame($negated, $choice?->negated === true);
    }

    #[TestWith(['SELECT 1 = ANY (ARRAY[1])', ComparisonOperator::Equal, false])]
    #[TestWith(['SELECT 1 OPERATOR(pg_catalog.>=) ANY (ARRAY[1])', ComparisonOperator::GreaterEqual, false])]
    #[TestWith(["SELECT 'a' LIKE ANY (ARRAY['b'])", PatternOperator::Like, false])]
    #[TestWith(["SELECT 'a' !~~* ANY (ARRAY['b'])", PatternOperator::ILike, true])]
    public function testReadClassifiesBuiltinOperators(string $sql, ComparisonOperator|PatternOperator $expected, bool $negated): void
    {
        $operator = (new DialectParser(Dialect::PostgreSql))->parse($sql)->find('subquery_Op')[0];
        $choice = QuantifiedOperator::read($operator, new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame($expected, $choice->operator);
        self::assertSame($negated, $choice->negated);
    }

    /**
     * @param list<string> $qualifier
     */
    #[TestWith(['SELECT 1 OPERATOR(geo.<->) ANY (ARRAY[1])', ['geo'], '<->'])]
    #[TestWith(['SELECT 1 << ANY (ARRAY[1])', [], '<<'])]
    public function testReadKeepsAnyOtherOperatorNamed(string $sql, array $qualifier, string $symbol): void
    {
        $operator = (new DialectParser(Dialect::PostgreSql))->parse($sql)->find('subquery_Op')[0];
        $choice = QuantifiedOperator::read($operator, new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertEquals(new QualifiedOperator($qualifier, $symbol), $choice->operator);
        self::assertFalse($choice->negated);
    }

    #[TestWith(['SELECT 1 OPERATOR(pg_catalog.||) ANY (ARRAY[1])'])]
    #[TestWith(['SELECT 1 * ALL (ARRAY[1])'])]
    public function testReadRejectsABuiltinThatDoesNotYieldBoolean(string $sql): void
    {
        $operator = (new DialectParser(Dialect::PostgreSql))->parse($sql)->find('subquery_Op')[0];
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::QuantifiedOperator->message());
        QuantifiedOperator::read($operator, new Scope(new Identifiers(Dialect::PostgreSql)));
    }
}
