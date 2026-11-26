<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;

#[CoversClass(IntegerLiteral::class)]
#[Small]
final class IntegerLiteralTest extends TestCase
{
    public function testAcceptsSignedIntegerBoundary(): void
    {
        self::assertSame('-9223372036854775808', (new IntegerLiteral('-9223372036854775808'))->decimal);
    }

    public function testRejectsOverflow(): void
    {
        $this->expectExceptionMessage('64-bit range');

        new IntegerLiteral('9223372036854775808');
    }
}
