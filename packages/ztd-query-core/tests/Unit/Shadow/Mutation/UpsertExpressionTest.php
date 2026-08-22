<?php

declare(strict_types=1);

namespace ZtdQuery\Tests\Unit\Shadow\Mutation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;

#[CoversClass(UpsertExpression::class)]
#[CoversClass(UpsertColumnSource::class)]
#[UsesClass(\ZtdQuery\Exception\UnsupportedSqlException::class)]
final class UpsertExpressionTest extends TestCase
{
    public function testEvaluatesTypedExpressionTree(): void
    {
        $expression = UpsertExpression::binary(
            UpsertExpressionKind::Add,
            UpsertExpression::column(UpsertColumnSource::Existing, 'quantity'),
            UpsertExpression::binary(
                UpsertExpressionKind::Multiply,
                UpsertExpression::column(UpsertColumnSource::Incoming, 'quantity'),
                UpsertExpression::literal(2),
            ),
        );

        self::assertSame(11, $expression->evaluate(['quantity' => 5], ['quantity' => 3], 'items'));
    }

    /** @return iterable<string, array{UpsertExpressionKind, mixed, mixed, mixed}> */
    public static function binaryProvider(): iterable
    {
        yield 'subtract' => [UpsertExpressionKind::Subtract, 8, 3, 5];
        yield 'multiply' => [UpsertExpressionKind::Multiply, 8, 3, 24];
        yield 'divide' => [UpsertExpressionKind::Divide, 8, 2, 4];
        yield 'modulo' => [UpsertExpressionKind::Modulo, 8, 3, 2];
        yield 'equal' => [UpsertExpressionKind::Equal, 8, 8, true];
        yield 'not equal' => [UpsertExpressionKind::NotEqual, 8, 3, true];
        yield 'less' => [UpsertExpressionKind::Less, 3, 8, true];
        yield 'less or equal' => [UpsertExpressionKind::LessOrEqual, 8, 8, true];
        yield 'greater' => [UpsertExpressionKind::Greater, 8, 3, true];
        yield 'greater or equal' => [UpsertExpressionKind::GreaterOrEqual, 8, 8, true];
        yield 'and' => [UpsertExpressionKind::And, true, null, null];
        yield 'or' => [UpsertExpressionKind::Or, false, true, true];
    }

    #[DataProvider('binaryProvider')]
    public function testEvaluatesBinaryKinds(
        UpsertExpressionKind $kind,
        mixed $left,
        mixed $right,
        mixed $expected,
    ): void {
        $expression = UpsertExpression::binary(
            $kind,
            UpsertExpression::literal($left),
            UpsertExpression::literal($right),
        );

        self::assertSame($expected, $expression->evaluate([], [], 'items'));
    }

    public function testEvaluatesUnaryKindsAndMatchesSqlTruth(): void
    {
        self::assertSame(
            -5,
            UpsertExpression::unary(UpsertExpressionKind::UnaryMinus, UpsertExpression::literal(5))
                ->evaluate([], [], 'items'),
        );
        self::assertSame(
            5,
            UpsertExpression::unary(UpsertExpressionKind::UnaryPlus, UpsertExpression::literal('5'))
                ->evaluate([], [], 'items'),
        );
        self::assertFalse(
            UpsertExpression::unary(UpsertExpressionKind::Not, UpsertExpression::literal(true))
                ->matches([], [], 'items'),
        );
        self::assertFalse(UpsertExpression::literal(null)->matches([], [], 'items'));
    }

    public function testRejectsInvalidTreeShapes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UpsertExpression::unary(UpsertExpressionKind::Add, UpsertExpression::literal(1));
    }

    public function testRejectsEmptyColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UpsertExpression::column(UpsertColumnSource::Existing, '');
    }

    public function testRejectsUnknownColumn(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        UpsertExpression::column(UpsertColumnSource::Existing, 'missing')->evaluate([], [], 'items');
    }

    public function testRejectsDivisionByZero(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        UpsertExpression::binary(
            UpsertExpressionKind::Divide,
            UpsertExpression::literal(1),
            UpsertExpression::literal(0),
        )->evaluate([], [], 'items');
    }
}
