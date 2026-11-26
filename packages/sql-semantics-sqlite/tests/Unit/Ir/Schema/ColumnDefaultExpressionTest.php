<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ArithmeticOperator;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\ColumnDefaultExpression;

#[CoversClass(ColumnDefaultExpression::class)]
#[Small]
final class ColumnDefaultExpressionTest extends TestCase
{
    public function testKeepsTheClosedOperandsAndOrder(): void
    {
        $earlier = new Arithmetic(ArithmeticOperator::Subtract, new IntegerLiteral('8'), new IntegerLiteral('2'));
        $later = new Arithmetic(ArithmeticOperator::Subtract, new IntegerLiteral('13'), $earlier);
        $default = new ColumnDefaultExpression($later);

        self::assertSame($later, $default->expression);
        self::assertSame($earlier, $later->right);
    }
}
