<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ArithmeticOperator;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ExpressionLowering::class)]
#[Small]
final class ExpressionLoweringTest extends TestCase
{
    public function testLowerPreservesArithmeticOperation(): void
    {
        $operation = (new Inspection())->inspect('SELECT (2 - 3)');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $result);

        $value = (new ExpressionLowering())->lower($result->expression, new SourceBinding($operation->binding(), $select->from));
        self::assertInstanceOf(Arithmetic::class, $value);
        self::assertSame(ArithmeticOperator::Subtract, $value->operator);
    }

    public function testChoicePreservesTheNumberedBaseAndSelectedResult(): void
    {
        $operation = (new Inspection())->inspect('SELECT CASE ?02 WHEN 3 THEN 7 ELSE 8 END');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $result);

        $value = (new ExpressionLowering())->lower($result->expression, new SourceBinding($operation->binding(), $select->from));
        self::assertInstanceOf(CaseExpression::class, $value);
        self::assertInstanceOf(Parameter::class, $value->base);
        self::assertSame('02', $value->base->label);
        self::assertCount(1, $value->branches);
    }
}
