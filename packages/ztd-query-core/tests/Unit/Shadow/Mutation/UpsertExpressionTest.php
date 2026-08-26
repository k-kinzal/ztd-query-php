<?php

declare(strict_types=1);

namespace ZtdQuery\Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;

#[CoversClass(UpsertExpression::class)]
#[CoversClass(UpsertColumnSource::class)]
#[UsesClass(UnsupportedSqlException::class)]
final class UpsertExpressionTest extends TestCase
{
    /**
     * Test evaluates typed expression tree.
     *
     */
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

    /**
     * @return iterable<string, array{UpsertExpressionKind, mixed, mixed, mixed}>
     */
    public static function binaryProvider(): iterable
    {
        yield 'subtract' => [UpsertExpressionKind::Subtract, 8, 3, 5];
        yield 'multiply' => [UpsertExpressionKind::Multiply, 8, 3, 24];
        yield 'divide' => [UpsertExpressionKind::Divide, 8, 2, 4];
        yield 'modulo' => [UpsertExpressionKind::Modulo, 8, 3, 2];
        yield 'equal' => [UpsertExpressionKind::Equal, 8, 3, false];
        yield 'equal boundary' => [UpsertExpressionKind::Equal, 8, 8, true];
        yield 'not equal' => [UpsertExpressionKind::NotEqual, 8, 3, true];
        yield 'not equal boundary' => [UpsertExpressionKind::NotEqual, 8, 8, false];
        yield 'less' => [UpsertExpressionKind::Less, 3, 8, true];
        yield 'less boundary' => [UpsertExpressionKind::Less, 8, 8, false];
        yield 'less or equal' => [UpsertExpressionKind::LessOrEqual, 8, 3, false];
        yield 'less or equal boundary' => [UpsertExpressionKind::LessOrEqual, 8, 8, true];
        yield 'greater' => [UpsertExpressionKind::Greater, 8, 3, true];
        yield 'greater boundary' => [UpsertExpressionKind::Greater, 8, 8, false];
        yield 'greater or equal' => [UpsertExpressionKind::GreaterOrEqual, 3, 8, false];
        yield 'greater or equal boundary' => [UpsertExpressionKind::GreaterOrEqual, 8, 8, true];
        yield 'and left' => [UpsertExpressionKind::And, false, true, false];
        yield 'and right' => [UpsertExpressionKind::And, true, false, false];
        yield 'or left' => [UpsertExpressionKind::Or, true, false, true];
        yield 'or right' => [UpsertExpressionKind::Or, false, true, true];
    }

    /**
     * Test evaluates binary kinds.
     *
     * @param UpsertExpressionKind $kind
     */
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

    /**
     * Test evaluates unary kinds and matches sql truth.
     *
     */
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

    /**
     * Test evaluates null numeric text and case insensitive column boundaries.
     *
     */
    public function testEvaluatesNullNumericTextAndCaseInsensitiveColumnBoundaries(): void
    {
        self::assertSame(3.5, UpsertExpression::unary(
            UpsertExpressionKind::UnaryPlus,
            UpsertExpression::literal('3.5'),
        )->evaluate([], [], 'items'));
        self::assertNull(UpsertExpression::unary(
            UpsertExpressionKind::UnaryMinus,
            UpsertExpression::literal(null),
        )->evaluate([], [], 'items'));
        self::assertNull(UpsertExpression::unary(
            UpsertExpressionKind::Not,
            UpsertExpression::literal(null),
        )->evaluate([], [], 'items'));
        self::assertSame(
            7,
            UpsertExpression::column(UpsertColumnSource::Existing, 'QuAnTiTy')
                ->evaluate(['quantity' => 7], [], 'items'),
        );
        self::assertSame(
            9,
            UpsertExpression::column(UpsertColumnSource::Incoming, 'quantity')
                ->evaluate([], ['quantity' => 9], 'items'),
        );
        self::assertTrue(UpsertExpression::binary(
            UpsertExpressionKind::Less,
            UpsertExpression::literal('alpha'),
            UpsertExpression::literal('beta'),
        )->matches([], [], 'items'));
        self::assertTrue(UpsertExpression::binary(
            UpsertExpressionKind::Equal,
            UpsertExpression::literal('2'),
            UpsertExpression::literal(2),
        )->matches([], [], 'items'));
        self::assertFalse(UpsertExpression::binary(
            UpsertExpressionKind::Equal,
            UpsertExpression::literal(true),
            UpsertExpression::literal(false),
        )->matches([], [], 'items'));
        self::assertTrue(UpsertExpression::binary(
            UpsertExpressionKind::Greater,
            UpsertExpression::literal(true),
            UpsertExpression::literal(false),
        )->matches([], [], 'items'));
    }

    /**
     * Test arithmetic null and floating point boundaries.
     *
     */
    public function testArithmeticNullAndFloatingPointBoundaries(): void
    {
        foreach ([
            UpsertExpressionKind::Add,
            UpsertExpressionKind::Subtract,
            UpsertExpressionKind::Multiply,
            UpsertExpressionKind::Divide,
            UpsertExpressionKind::Modulo,
        ] as $kind) {
            self::assertNull(UpsertExpression::binary(
                $kind,
                UpsertExpression::literal(null),
                UpsertExpression::literal(2),
            )->evaluate([], [], 'items'));
            self::assertNull(UpsertExpression::binary(
                $kind,
                UpsertExpression::literal(2),
                UpsertExpression::literal(null),
            )->evaluate([], [], 'items'));
        }

        self::assertSame(3.5, UpsertExpression::binary(
            UpsertExpressionKind::Add,
            UpsertExpression::literal('1.5'),
            UpsertExpression::literal(2.0),
        )->evaluate([], [], 'items'));
        self::assertSame(2.5, UpsertExpression::binary(
            UpsertExpressionKind::Divide,
            UpsertExpression::literal(5),
            UpsertExpression::literal(2),
        )->evaluate([], [], 'items'));
    }

    /**
     * Test three valued boolean boundaries.
     *
     */
    public function testThreeValuedBooleanBoundaries(): void
    {
        self::assertNull(UpsertExpression::binary(
            UpsertExpressionKind::And,
            UpsertExpression::literal(true),
            UpsertExpression::literal(null),
        )->evaluate([], [], 'items'));
        self::assertFalse(UpsertExpression::binary(
            UpsertExpressionKind::And,
            UpsertExpression::literal(false),
            UpsertExpression::literal(null),
        )->evaluate([], [], 'items'));
        self::assertNull(UpsertExpression::binary(
            UpsertExpressionKind::Or,
            UpsertExpression::literal(false),
            UpsertExpression::literal(null),
        )->evaluate([], [], 'items'));
        self::assertTrue(UpsertExpression::binary(
            UpsertExpressionKind::Or,
            UpsertExpression::literal(true),
            UpsertExpression::literal(null),
        )->evaluate([], [], 'items'));
        self::assertNull(UpsertExpression::binary(
            UpsertExpressionKind::And,
            UpsertExpression::literal(null),
            UpsertExpression::literal(true),
        )->evaluate([], [], 'items'));
        self::assertNull(UpsertExpression::binary(
            UpsertExpressionKind::And,
            UpsertExpression::literal(null),
            UpsertExpression::literal(null),
        )->evaluate([], [], 'items'));
        self::assertNull(UpsertExpression::binary(
            UpsertExpressionKind::Or,
            UpsertExpression::literal(null),
            UpsertExpression::literal(false),
        )->evaluate([], [], 'items'));
        self::assertNull(UpsertExpression::binary(
            UpsertExpressionKind::Or,
            UpsertExpression::literal(null),
            UpsertExpression::literal(null),
        )->evaluate([], [], 'items'));
    }

    /**
     * Test comparison returns null when either operand is null.
     *
     */
    public function testComparisonReturnsNullWhenEitherOperandIsNull(): void
    {
        self::assertNull(UpsertExpression::binary(
            UpsertExpressionKind::Equal,
            UpsertExpression::literal(null),
            UpsertExpression::literal(1),
        )->evaluate([], [], 'items'));
        self::assertNull(UpsertExpression::binary(
            UpsertExpressionKind::Equal,
            UpsertExpression::literal(1),
            UpsertExpression::literal(null),
        )->evaluate([], [], 'items'));
    }

    /**
     * Test rejects invalid tree shapes.
     *
     */
    public function testRejectsInvalidTreeShapes(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        UpsertExpression::unary(UpsertExpressionKind::Add, UpsertExpression::literal(1));
    }

    /**
     * Test rejects empty column.
     *
     */
    public function testRejectsEmptyColumn(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        UpsertExpression::column(UpsertColumnSource::Existing, '');
    }

    /**
     * Test rejects unknown column.
     *
     */
    public function testRejectsUnknownColumn(): void
    {
        try {
            UpsertExpression::column(UpsertColumnSource::Existing, 'missing')->evaluate([], [], 'items');
            self::fail('Unknown UPSERT column must be rejected');
        } catch (UnsupportedSqlException $exception) {
            self::assertSame('unknown UPSERT column missing', $exception->getSql());
        }
    }

    /**
     * Test rejects division by zero.
     *
     */
    public function testRejectsDivisionByZero(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        UpsertExpression::binary(
            UpsertExpressionKind::Divide,
            UpsertExpression::literal(1),
            UpsertExpression::literal(0),
        )->evaluate([], [], 'items');
    }

    /**
     * Test accepts floating point one as divisor.
     *
     */
    public function testAcceptsFloatingPointOneAsDivisor(): void
    {
        self::assertSame(8.0, UpsertExpression::binary(
            UpsertExpressionKind::Divide,
            UpsertExpression::literal(8),
            UpsertExpression::literal(1.0),
        )->evaluate([], [], 'items'));
    }

    /**
     * Test rejects modulo by zero.
     *
     */
    public function testRejectsModuloByZero(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        UpsertExpression::binary(
            UpsertExpressionKind::Modulo,
            UpsertExpression::literal(1),
            UpsertExpression::literal(0),
        )->evaluate([], [], 'items');
    }

    /**
     * Test rejects non numeric arithmetic operand.
     *
     */
    public function testRejectsNonNumericArithmeticOperand(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        UpsertExpression::binary(
            UpsertExpressionKind::Add,
            UpsertExpression::literal('not numeric'),
            UpsertExpression::literal(1),
        )->evaluate([], [], 'items');
    }

    /**
     * Test rejects numeric and text comparison.
     *
     */
    public function testRejectsNumericAndTextComparison(): void
    {
        try {
            UpsertExpression::binary(
                UpsertExpressionKind::Equal,
                UpsertExpression::literal(1),
                UpsertExpression::literal('text'),
            )->evaluate([], [], 'items');
            self::fail('Numeric and text operands must not be compared');
        } catch (UnsupportedSqlException $exception) {
            self::assertSame('incomparable UPSERT operands', $exception->getSql());
        }
    }
}
