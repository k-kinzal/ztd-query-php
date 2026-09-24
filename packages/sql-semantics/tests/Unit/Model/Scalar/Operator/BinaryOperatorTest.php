<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Operator\BinaryOperator;

#[CoversClass(BinaryOperator::class)]
final class BinaryOperatorTest extends TestCase
{
    public function testRepresentsEveryBinaryOperatorSpelling(): void
    {
        self::assertSame(
            ['+', '-', '*', '/', '%', 'DIV', 'MOD', '^', '&', '|', 'XOR', '<<', '>>', '||', '=', '<>', '!=', '<', '>', '<=', '>=', 'AND', 'OR', 'IS', 'IS NOT', '<=>', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM', '->', '->>', 'SOUNDS LIKE'],
            array_column(BinaryOperator::cases(), 'value'),
        );
    }

    #[TestWith(['<=>', BinaryOperator::NullSafeEqual])]
    #[TestWith(['IS NOT DISTINCT FROM', BinaryOperator::NotDistinct])]
    #[TestWith(['->>', BinaryOperator::JsonText])]
    #[TestWith(['SOUNDS LIKE', BinaryOperator::SoundsLike])]
    public function testClassifiesAnOperatorFromItsSpelling(string $spelling, BinaryOperator $operator): void
    {
        self::assertSame($operator, BinaryOperator::from($spelling));
    }

    #[TestWith(['NOT'])]
    #[TestWith(['LIKE'])]
    public function testLeavesUnaryAndPatternOperatorsUnclassified(string $spelling): void
    {
        self::assertNull(BinaryOperator::tryFrom($spelling));
    }
}
