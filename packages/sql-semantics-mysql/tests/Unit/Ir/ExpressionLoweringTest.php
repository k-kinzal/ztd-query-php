<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Arithmetic;
use SqlSemantics\Platform\MySql\Ir\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic as ParsedArithmetic;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ExpressionLowering::class)]
#[Small]
final class ExpressionLoweringTest extends TestCase
{
    public function testLowerPreservesArithmeticOperation(): void
    {
        $operation = (new Inspection())->inspect('SELECT (2 - 3)');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $result);

        $value = (new ExpressionLowering())->lower($result->expression, new SourceBinding($operation->binding(), $select->from));
        self::assertInstanceOf(Arithmetic::class, $value);
        self::assertSame(ArithmeticOperator::Subtract, $value->operator);
    }

    public function testBinaryRetainsTheOrderOfOperands(): void
    {
        $operation = (new Inspection())->inspect('SELECT 7 - 3 AS difference');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $field = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $field);
        self::assertInstanceOf(ParsedArithmetic::class, $field->expression);

        $result = (new ExpressionLowering())->binary($field->expression, new SourceBinding($operation->binding(), $select->from), false);

        self::assertSame(ArithmeticOperator::Subtract, $result->operator);
        self::assertInstanceOf(IntegerLiteral::class, $result->left);
        self::assertSame('7', $result->left->decimal);
        self::assertInstanceOf(IntegerLiteral::class, $result->right);
        self::assertSame('3', $result->right->decimal);
    }
}
