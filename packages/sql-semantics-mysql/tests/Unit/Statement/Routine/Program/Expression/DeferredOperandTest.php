<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;

#[CoversClass(DeferredOperand::class)]
#[Medium]
final class DeferredOperandTest extends TestCase
{
    /**
     * @return iterable<string, array{DeferredOperand, int}>
     */
    public static function providerOperands(): iterable
    {
        $value = new DeferredArithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')]));
        yield 'arithmetic' => [$value, 2];
        yield 'grouped' => [new DeferredGrouped($value), 1];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerOperands')]
    public function testOperandsKeepsTheDeclaredPreparationOrder(DeferredOperand $value, int $count): void
    {
        self::assertCount($count, $value->operands());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerOperands')]
    public function testProblemPreservesTheScalarUseConstraint(DeferredOperand $value, int $count): void
    {
        self::assertSame([1, 2, $count], [$value->problem()->expected, $value->problem()->actual, count($value->operands())]);
    }
}
