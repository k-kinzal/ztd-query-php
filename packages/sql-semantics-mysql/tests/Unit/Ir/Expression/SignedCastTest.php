<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;

#[CoversClass(SignedCast::class)]
#[Small]
final class SignedCastTest extends TestCase
{
    public function testOwnsTheTypedOccurrenceOfNull(): void
    {
        $input = new NullLiteral();
        $inner = new SignedCast($input);
        $outer = new SignedCast($inner);

        self::assertSame($input, $inner->operand);
        self::assertSame($inner, $outer->operand);
    }
}
