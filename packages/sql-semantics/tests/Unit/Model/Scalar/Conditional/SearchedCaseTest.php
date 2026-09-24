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
use SqlSemantics\Model\Scalar\Conditional\SearchedCase;
use SqlSemantics\Model\Scalar\Conditional\When;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(SearchedCase::class)]
#[Medium]
final class SearchedCaseTest extends TestCase
{
    public function testInputsFlattensEachBranchInOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT CASE WHEN 1 = 1 THEN 2 WHEN 2 = 2 THEN 3 END');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $case = $statement->outputs[0]->expression;
        self::assertInstanceOf(SearchedCase::class, $case);
        self::assertCount(2, $case->branches);
        self::assertNull($case->otherwise);
        self::assertSame([$case->branches[0]->test, $case->branches[0]->result, $case->branches[1]->test, $case->branches[1]->result], $case->inputs());
        self::assertSame(Nullability::MaybeNull, $case->nullability);
        self::assertSame('SELECT CASE WHEN (1 = 1) THEN 2 WHEN (2 = 2) THEN 3 END', $statement->toString());
        self::assertSame('SELECT CASE WHEN (1 = 1) THEN 2 WHEN (2 = 2) THEN 3 END', $binder->bind($statement->toString())->toString());
    }

    public function testInputsAppendsTheElseResult(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT CASE WHEN 1 = 1 THEN 2 ELSE 3 END');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $case = $statement->outputs[0]->expression;
        self::assertInstanceOf(SearchedCase::class, $case);
        self::assertNotNull($case->otherwise);
        self::assertSame('3', $case->otherwise->spelling());
        self::assertSame([$case->branches[0]->test, $case->branches[0]->result, $case->otherwise], $case->inputs());
        self::assertSame(Nullability::NotNull, $case->nullability);
    }

    public function testInputsRequiresAtLeastOneBranch(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new SearchedCase($origin->facts, $origin->source, [], $origin);
    }

    public function testSpellingIsTheCaseKeyword(): void
    {
        $result = Expression::literal(1, Dialect::PostgreSql);
        $case = new SearchedCase($result->facts, $result->source, [new When(Expression::literal(true, Dialect::PostgreSql), $result)], null);
        self::assertSame('CASE', $case->spelling());
        self::assertSame('CASE WHEN TRUE THEN 1 END', $case->structure()->toString());
    }

    public function testWithFactsKeepsTheBranchesAndElse(): void
    {
        $result = Expression::literal(1, Dialect::PostgreSql);
        $otherwise = Expression::literal(2, Dialect::PostgreSql);
        $branch = new When(Expression::literal(true, Dialect::PostgreSql), $result);
        $case = new SearchedCase($result->facts, $result->source, [$branch], $otherwise);
        $copy = $case->withFacts(new ExpressionFacts($case->type, Nullability::MaybeNull));
        self::assertNotSame($case, $copy);
        self::assertSame([$branch], $copy->branches);
        self::assertSame($otherwise, $copy->otherwise);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $case->nullability);
    }
}
