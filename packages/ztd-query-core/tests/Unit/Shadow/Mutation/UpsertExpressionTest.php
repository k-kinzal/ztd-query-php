<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenStream::class)]
#[UsesClass(UnsupportedSqlException::class)]
#[CoversClass(UpsertExpression::class)]
final class UpsertExpressionTest extends TestCase
{
    public function testEvaluatesExistingAndIncomingReferencesWithPrecedence(): void
    {
        $expression = UpsertExpression::parse('items.quantity + VALUES(`quantity`) * 2');

        self::assertSame(110, $expression->evaluate(
            ['id' => 1, 'quantity' => 100],
            ['id' => 1, 'quantity' => 5],
            'items',
        ));
    }

    public function testEvaluatesExcludedAndUnqualifiedReferences(): void
    {
        $expression = UpsertExpression::parse('(quantity - excluded.quantity) / 5');

        self::assertSame(19, $expression->evaluate(
            ['quantity' => '100'],
            ['quantity' => 5],
            'items',
        ));
    }

    public function testEvaluatesLiteralsComparisonAndSqlNullLogic(): void
    {
        $comparison = UpsertExpression::parse("score >= 80 AND excluded.name <> 'blocked'");
        $nullArithmetic = UpsertExpression::parse('score + NULL');

        self::assertTrue($comparison->evaluate(
            ['score' => 90],
            ['name' => 'ready'],
            'items',
        ));
        self::assertNull($nullArithmetic->evaluate(['score' => 90], [], 'items'));
        self::assertTrue($comparison->matches(['score' => 90], ['name' => 'ready'], 'items'));
        self::assertFalse($comparison->matches(['score' => null], ['name' => 'ready'], 'items'));
    }

    public function testEvaluatesQuotedIdentifiersAndEscapedStringLiteral(): void
    {
        $column = UpsertExpression::parse('"items"."Quantity" + EXCLUDED."Quantity"');
        $literal = UpsertExpression::parse("'it''s ready'");

        self::assertSame(7, $column->evaluate(['quantity' => 5], ['quantity' => 2], 'items'));
        self::assertSame("it's ready", $literal->evaluate([], [], 'items'));
    }

    public function testRejectsUnsupportedFunctionsInsteadOfStoringRawSql(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        UpsertExpression::parse('COALESCE(quantity, 0)');
    }
}
