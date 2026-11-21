<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Arithmetic;
use SqlSemantics\Platform\MySql\Ir\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(ExpressionLowering::class)]
#[Small]
final class ExpressionLoweringTest extends TestCase
{
    public function testLowerPreservesArithmeticOperation(): void
    {
        $operation = (new Semantics())->inspect('SELECT (2 - 3)');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $result);

        $value = (new ExpressionLowering())->lower($result->expression, new SourceBinding($operation, $select->from));
        self::assertInstanceOf(Arithmetic::class, $value);
        self::assertSame(ArithmeticOperator::Subtract, $value->operator);
    }
}
