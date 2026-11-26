<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRowOperand;

#[CoversClass(ValueRowOperand::class)]
#[Small]
final class ValueRowOperandTest extends TestCase
{
    public function testCheckedKeepsTheAdmittedObject(): void
    {
        $operand = new CollatedExpression(new NullLiteral(), Collation::Binary);

        self::assertSame($operand, (new ValueRowOperand())->checked($operand));
    }

    public function testAtomRetainsTheExactNativeMarker(): void
    {
        $operand = new \SqlSemantics\Platform\Sqlite\Ir\Parameter('03');

        self::assertSame($operand, (new ValueRowOperand())->atom($operand));
    }
}
