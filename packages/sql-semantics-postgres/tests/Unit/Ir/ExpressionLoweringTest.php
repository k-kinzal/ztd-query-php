<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\ArithmeticOperator;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(ExpressionLowering::class)]
#[Small]
final class ExpressionLoweringTest extends TestCase
{
    public function testLowerPreservesArithmeticOperation(): void
    {
        $operation = (new Inspection())->inspect('SELECT (2 - 3)');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $result);

        $value = (new ExpressionLowering())->lower($result->expression, new SourceBinding($operation->binding(), $select->from), true);
        self::assertInstanceOf(Arithmetic::class, $value);
        self::assertSame(ArithmeticOperator::Subtract, $value->operator);
    }

    public function testBinaryResolvesAnExplicitlyQualifiedQuotient(): void
    {
        $operation = (new Inspection())->inspect('SELECT 9 OPERATOR(pg_catalog./) 4');
        self::assertInstanceOf(Select::class, $operation->statement);
        $target = $operation->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        self::assertInstanceOf(BinaryOperation::class, $target->expression);
        $value = (new ExpressionLowering())->binary($target->expression, new SourceBinding($operation->binding(), null), false);

        self::assertSame(ArithmeticOperator::Divide, $value->operator);
    }

    public function testIntegerLiteralDecodesTheSignedMinimum(): void
    {
        $operation = (new Inspection())->inspect('SELECT -2147483648');
        self::assertInstanceOf(Select::class, $operation->statement);
        $target = $operation->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);

        $value = (new ExpressionLowering())->integerLiteral($target->expression, true);
        self::assertInstanceOf(IntegerLiteral::class, $value);
        self::assertSame('-2147483648', $value->decimal);
    }
}
