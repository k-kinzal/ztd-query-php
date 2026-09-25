<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\SimpleCase;
use SqlSemantics\Model\Scalar\Conditional\When;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(SimpleCase::class)]
#[Medium]
final class SimpleCaseTest extends TestCase
{
    public function testInputsStartsWithTheComparedValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT CASE 1 WHEN 1 THEN 2 ELSE 3 END');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $case = $statement->outputs[0]->expression;
        self::assertInstanceOf(SimpleCase::class, $case);
        self::assertSame('1', $case->value->spelling());
        self::assertCount(1, $case->branches);
        self::assertNotNull($case->otherwise);
        self::assertSame([$case->value, $case->branches[0]->test, $case->branches[0]->result, $case->otherwise], $case->inputs());
        self::assertSame(Nullability::NotNull, $case->nullability);
        self::assertSame('SELECT CASE 1 WHEN 1 THEN 2 ELSE 3 END', $statement->toString());
        self::assertSame('SELECT CASE 1 WHEN 1 THEN 2 ELSE 3 END', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testInputsOmitsAMissingElse(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT CASE 1 WHEN 1 THEN 2 END');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $case = $statement->outputs[0]->expression;
        self::assertInstanceOf(SimpleCase::class, $case);
        self::assertNull($case->otherwise);
        self::assertSame([$case->value, $case->branches[0]->test, $case->branches[0]->result], $case->inputs());
        self::assertSame(Nullability::MaybeNull, $case->nullability);
    }

    public function testInputsRequiresAtLeastOneBranch(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new SimpleCase($value->facts, $value->source, $value, [], null);
    }

    public function testSpellingIsTheCaseKeyword(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $branch = new When(Expression::literal(1, Dialect::PostgreSql), Expression::literal(2, Dialect::PostgreSql));
        $case = new SimpleCase($value->facts, $value->source, $value, [$branch], Expression::literal(3, Dialect::PostgreSql));
        self::assertSame('CASE', $case->spelling());
        self::assertSame('CASE 1 WHEN 1 THEN 2 ELSE 3 END', $case->structure()->toString());
    }

    public function testWithFactsKeepsTheValueBranchesAndElse(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $branch = new When(Expression::literal(1, Dialect::PostgreSql), Expression::literal(2, Dialect::PostgreSql));
        $otherwise = Expression::literal(3, Dialect::PostgreSql);
        $case = new SimpleCase($value->facts, $value->source, $value, [$branch], $otherwise);
        $copy = $case->withFacts(new ExpressionFacts($case->type, Nullability::MaybeNull));
        self::assertNotSame($case, $copy);
        self::assertSame($value, $copy->value);
        self::assertSame([$branch], $copy->branches);
        self::assertSame($otherwise, $copy->otherwise);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $case->nullability);
    }
}
