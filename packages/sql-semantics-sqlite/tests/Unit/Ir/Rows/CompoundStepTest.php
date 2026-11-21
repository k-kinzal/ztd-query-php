<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundOperator;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundStep;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;

#[CoversClass(CompoundStep::class)]
#[Small]
final class CompoundStepTest extends TestCase
{
    public function testKeepsTheOperatorAndItsRightOperand(): void
    {
        $operand = new ValuesQuery([new ValueRow([new IntegerLiteral('1')])]);
        $step = new CompoundStep(CompoundOperator::Intersect, $operand);

        self::assertSame(CompoundOperator::Intersect, $step->operator);
        self::assertSame($operand, $step->query);
    }
}
