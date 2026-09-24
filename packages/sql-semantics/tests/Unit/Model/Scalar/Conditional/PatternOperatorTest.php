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
use SqlSemantics\Model\Scalar\Conditional\PatternMatch;
use SqlSemantics\Model\Scalar\Conditional\PatternOperator;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PatternOperator::class)]
#[Medium]
final class PatternOperatorTest extends TestCase
{
    public function testRepresentsEveryPatternLanguage(): void
    {
        self::assertSame(['LIKE', 'ILIKE', 'GLOB', 'REGEXP', 'MATCH', 'SIMILAR TO'], array_column(PatternOperator::cases(), 'value'));
    }

    #[TestWith([Dialect::MySql, "SELECT 'a' RLIKE 'b'", PatternOperator::Regexp])]
    #[TestWith([Dialect::Sqlite, "SELECT 'a' GLOB 'b'", PatternOperator::Glob])]
    #[TestWith([Dialect::PostgreSql, "SELECT 'a' SIMILAR TO 'b'", PatternOperator::SimilarTo])]
    public function testClassifiesABoundPatternPredicate(Dialect $dialect, string $sql, PatternOperator $operator): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $match = $statement->outputs[0]->expression;
        self::assertInstanceOf(PatternMatch::class, $match);
        self::assertSame($operator, $match->operator);
    }
}
