<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\Expression\UnsignedCast;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;

#[CoversClass(UnsignedCast::class)]
#[Small]
final class UnsignedCastTest extends TestCase
{
    public function testKeepsTheTypedOperand(): void
    {
        $inner = new SignedCast(new IntegerLiteral('-17'));
        $value = new UnsignedCast($inner);

        self::assertSame($inner, $value->operand);
    }
}
