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
use SqlSemantics\Model\Scalar\Conditional\PatternMatch;
use SqlSemantics\Model\Scalar\Conditional\PatternOperator;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(PatternMatch::class)]
#[Medium]
final class PatternMatchTest extends TestCase
{
    public function testInputsIncludesTheEscapeAfterThePattern(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT 'a' NOT ILIKE 'b' ESCAPE '!'");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $match = $statement->outputs[0]->expression;
        self::assertInstanceOf(PatternMatch::class, $match);
        self::assertSame(PatternOperator::ILike, $match->operator);
        self::assertTrue($match->negated);
        self::assertNotNull($match->escape);
        self::assertSame("'!'", $match->escape->spelling());
        self::assertSame([$match->value, $match->pattern, $match->escape], $match->inputs());
        self::assertSame("SELECT ('a' NOT ILIKE 'b' ESCAPE '!')", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame("SELECT ('a' NOT ILIKE 'b' ESCAPE '!')", (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testInputsOmitsAMissingEscape(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT 'a' LIKE 'b'");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $match = $statement->outputs[0]->expression;
        self::assertInstanceOf(PatternMatch::class, $match);
        self::assertNull($match->escape);
        self::assertFalse($match->negated);
        self::assertSame([$match->value, $match->pattern], $match->inputs());
        self::assertSame('boolean', $match->type->name);
    }

    #[TestWith([PatternOperator::Like, false, 'LIKE'])]
    #[TestWith([PatternOperator::SimilarTo, true, 'NOT SIMILAR TO'])]
    #[TestWith([PatternOperator::Glob, false, 'GLOB'])]
    public function testSpellingPrefixesNegationToTheOperator(PatternOperator $operator, bool $negated, string $spelling): void
    {
        $value = Expression::literal('a', Dialect::PostgreSql);
        $match = new PatternMatch($value->facts, $value->source, $value, Expression::literal('b', Dialect::PostgreSql), null, $negated, $operator);
        self::assertSame($spelling, $match->spelling());
        self::assertSame("('a' " . $spelling . " 'b')", $match->structure()->toString());
    }

    public function testRejectsAPatternFromAnotherDialect(): void
    {
        $value = Expression::literal('a', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new PatternMatch($value->facts, $value->source, $value, Expression::literal('b', Dialect::Sqlite), null, false, PatternOperator::Like);
    }

    public function testWithFactsKeepsTheOperandsAndOperator(): void
    {
        $value = Expression::literal('a', Dialect::PostgreSql);
        $pattern = Expression::literal('b', Dialect::PostgreSql);
        $escape = Expression::literal('!', Dialect::PostgreSql);
        $match = new PatternMatch($value->facts, $value->source, $value, $pattern, $escape, true, PatternOperator::Regexp);
        $copy = $match->withFacts(new ExpressionFacts($match->type, Nullability::MaybeNull));
        self::assertNotSame($match, $copy);
        self::assertSame([$value, $pattern, $escape], $copy->inputs());
        self::assertTrue($copy->negated);
        self::assertSame(PatternOperator::Regexp, $copy->operator);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $match->nullability);
    }
}
