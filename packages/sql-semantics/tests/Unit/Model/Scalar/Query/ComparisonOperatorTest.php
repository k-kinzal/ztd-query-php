<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Query\ComparisonOperator;
use SqlSemantics\Model\Scalar\Query\QuantifiedComparison;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ComparisonOperator::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ComparisonOperatorTest extends TestCase
{
    #[TestWith(['=', ComparisonOperator::Equal])]
    #[TestWith(['<>', ComparisonOperator::NotEqual])]
    #[TestWith(['!=', ComparisonOperator::NotEqualBang])]
    #[TestWith(['<', ComparisonOperator::Less])]
    #[TestWith(['>', ComparisonOperator::Greater])]
    #[TestWith(['<=', ComparisonOperator::LessEqual])]
    #[TestWith(['>=', ComparisonOperator::GreaterEqual])]
    public function testBindsEachWrittenOperatorOfAQuantifiedComparison(string $operator, ComparisonOperator $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 ' . $operator . ' ALL (SELECT 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $comparison = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $comparison);
        self::assertSame($expected, $comparison->operator);
        self::assertSame($operator, $expected->value);
        self::assertSame('SELECT (1 ' . $operator . ' ALL (SELECT 2))', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindsTheMySqlNullSafeEquality(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1 <=> ANY (SELECT 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $comparison = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $comparison);
        self::assertSame(ComparisonOperator::NullSafeEqual, $comparison->operator);
        self::assertSame('SELECT (1 <=> ANY(SELECT 2))', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testSpellsEachOperatorAsWrittenInSql(): void
    {
        self::assertSame(['=', '<>', '!=', '<', '>', '<=', '>=', '<=>'], array_map(static fn (ComparisonOperator $operator): string => $operator->value, ComparisonOperator::cases()));
    }
}
