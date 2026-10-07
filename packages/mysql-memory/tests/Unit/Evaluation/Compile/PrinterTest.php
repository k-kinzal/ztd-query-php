<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Printer;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;

#[CoversClass(Printer::class)]
#[Small]
final class PrinterTest extends TestCase
{
    public function testExpressionParenthesizesEachOperator(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new Arithmetic(ArithmeticOperator::Multiply, new NumberLiteral('2'), new NumberLiteral('3')));

        self::assertSame('(1 + (2 * 3))', (new Printer())->expression($expression));
    }

    public function testExpressionDropsTheParenthesesWrittenAroundAnOperand(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Minus, new Grouped(new NumberLiteral('1')), new NumberLiteral('2'));

        self::assertSame('(1 - 2)', (new Printer())->expression($expression));
    }

    public function testExpressionWritesAUnaryOperatorBeforeItsParenthesizedOperand(): void
    {
        self::assertSame('~(0)', (new Printer())->expression(new Unary(UnaryOperator::Invert, new NumberLiteral('0'))));
    }

    public function testExpressionQuotesAStringAndWritesNull(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Plus, new StringLiteral(["it's"]), new NullLiteral());

        self::assertSame("('it\\'s' + NULL)", (new Printer())->expression($expression));
    }

    public function testExpressionWritesAFunctionCallInLowerCase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT UNSIGNED value is out of range in '(18446744073709551615 + abs(1))'");

        $session->query('SELECT 18446744073709551615 + ABS(1)');
    }

    public function testExpressionWritesACastInLowerCase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT UNSIGNED value is out of range in '(cast(1 as unsigned) - 2)'");

        $session->query('SELECT CAST(1 AS UNSIGNED) - 2');
    }

    public function testExpressionWritesAnInvertedOperandInAMessage(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT UNSIGNED value is out of range in '(~(0) * 2)'");

        $session->query('SELECT ~0 * 2');
    }
}
