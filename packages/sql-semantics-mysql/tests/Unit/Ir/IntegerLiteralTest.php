<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;

#[CoversClass(IntegerLiteral::class)]
#[Small]
final class IntegerLiteralTest extends TestCase
{
    public function testAcceptsSignedIntegerBoundary(): void
    {
        self::assertSame('-2147483648', (new IntegerLiteral('-2147483648'))->decimal);
    }

    public function testRejectsOverflow(): void
    {
        $this->expectExceptionMessage('32-bit range');

        new IntegerLiteral('2147483648');
    }

    public function testToStringPreservesTheEffectiveFieldWidth(): void
    {
        self::assertSame(['-0002', '-000', '0004'], [
            (new IntegerLiteral('-2', 3))->toString(),
            (new IntegerLiteral('0', 2, true))->toString(),
            (new IntegerLiteral('4', 3))->toString(),
        ]);
    }
}
