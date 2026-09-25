<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\ArrayComparison;
use SqlSemantics\Model\Scalar\Conditional\PatternOperator;
use SqlSemantics\Model\Scalar\Query\ComparisonOperator;
use SqlSemantics\Model\Scalar\Query\Quantifier;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ArrayComparison::class)]
#[Medium]
final class ArrayComparisonTest extends TestCase
{
    public function testInputsListTheValueBeforeTheArray(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT 1 != ALL (ARRAY[1]), 'a' NOT ILIKE SOME ('{b}'::text[])");
        self::assertInstanceOf(BoundSelect::class, $query);
        $comparison = $query->outputs[0]->expression;
        self::assertInstanceOf(ArrayComparison::class, $comparison);
        self::assertSame(ComparisonOperator::NotEqual, $comparison->operator);
        self::assertSame(Quantifier::All, $comparison->quantifier);
        self::assertSame([$comparison->value, $comparison->array], $comparison->inputs());
        self::assertSame('boolean', $comparison->type->name);
        self::assertSame(Nullability::MaybeNull, $comparison->nullability);
        $pattern = $query->outputs[1]->expression;
        self::assertInstanceOf(ArrayComparison::class, $pattern);
        self::assertSame(PatternOperator::ILike, $pattern->operator);
        self::assertTrue($pattern->negated);
        self::assertSame("SELECT (1 <> ALL (ARRAY[1])), ('a' NOT ILIKE SOME(CAST('{b}' AS text [])))", (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    #[TestWith([ComparisonOperator::NullSafeEqual, false])]
    #[TestWith([ComparisonOperator::Equal, true])]
    #[TestWith([PatternOperator::Regexp, false])]
    public function testInputsRejectAnOperatorPostgreSqlDoesNotQuantify(ComparisonOperator|PatternOperator $operator, bool $negated): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new ArrayComparison($value->source, $value, $operator, $negated, Quantifier::Any, $value);
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new ArrayComparison($value->source, $value, ComparisonOperator::Equal, false, Quantifier::Any, $value);
    }

    public function testSpellingJoinsNegationOperatorAndQuantifier(): void
    {
        $value = Expression::literal('a', Dialect::PostgreSql);
        self::assertSame('NOT LIKE ANY', (new ArrayComparison($value->source, $value, PatternOperator::Like, true, Quantifier::Any, $value))->spelling());
        self::assertSame('< SOME', (new ArrayComparison($value->source, $value, ComparisonOperator::Less, false, Quantifier::Some, $value))->spelling());
    }

    public function testWithFactsPreservesTheOperands(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $comparison = new ArrayComparison($value->source, $value, ComparisonOperator::Equal, false, Quantifier::Any, $value);
        $copy = $comparison->withFacts($comparison->facts);
        self::assertNotSame($comparison, $copy);
        self::assertSame(ComparisonOperator::Equal, $copy->operator);
        $this->expectException(InvalidStructure::class);
        $comparison->withFacts($value->facts);
    }

    public function testSpellingNamesAnExplicitOperator(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $comparison = new ArrayComparison($value->source, $value, new \SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator(['geo'], '<->'), false, Quantifier::All, $value);
        self::assertSame('OPERATOR(geo.<->) ALL', $comparison->spelling());
        $this->expectException(InvalidStructure::class);
        new ArrayComparison($value->source, $value, $comparison->operator, true, Quantifier::All, $value);
    }
}
