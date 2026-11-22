<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ArithmeticOperator;

#[CoversClass(ArithmeticOperator::class)]
#[Small]
final class ArithmeticOperatorTest extends TestCase
{
    public function testCasesMatchTheSupportedArithmetic(): void
    {
        self::assertSame(['+', '-', '*', 'DIV', '%'], array_map(
            static fn (ArithmeticOperator $operator): string => $operator->value,
            ArithmeticOperator::cases(),
        ));
    }
}
