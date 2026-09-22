<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Operator\UnaryOperator;

#[CoversClass(UnaryOperator::class)]
final class UnaryOperatorTest extends TestCase
{
    #[TestWith([UnaryOperator::IsTrue, true])]
    #[TestWith([UnaryOperator::IsNotTrue, true])]
    #[TestWith([UnaryOperator::IsFalse, true])]
    #[TestWith([UnaryOperator::IsNotFalse, true])]
    #[TestWith([UnaryOperator::IsUnknown, true])]
    #[TestWith([UnaryOperator::IsNotUnknown, true])]
    #[TestWith([UnaryOperator::IsNull, false])]
    #[TestWith([UnaryOperator::Not, false])]
    public function testTruthTestClassifiesThePredicateInput(UnaryOperator $operator, bool $truth): void
    {
        self::assertSame($truth, $operator->truthTest());
    }

    #[TestWith([UnaryOperator::IsTrue, true])]
    #[TestWith([UnaryOperator::IsNull, true])]
    #[TestWith([UnaryOperator::IsNotNull, true])]
    #[TestWith([UnaryOperator::Positive, false])]
    public function testPostfixKeepsPredicateOperatorsAfterTheirOperand(UnaryOperator $operator, bool $postfix): void
    {
        self::assertSame($postfix, $operator->postfix());
    }
}
