<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\RealLiteral;

#[CoversClass(RealLiteral::class)]
#[Small]
final class RealLiteralTest extends TestCase
{
    public function testKeepsNegativeZeroAndTheWrittenDecimalParts(): void
    {
        $value = new RealLiteral('', '0', '+0', true);

        self::assertSame('', $value->whole);
        self::assertSame('0', $value->fraction);
        self::assertSame('+0', $value->exponent);
        self::assertTrue($value->negative);
    }

    public function testRejectsAnIntegerInThePointFreeRealRole(): void
    {
        $this->expectExceptionMessage('signed INTEGER boundary');

        new RealLiteral('9223372036854775808', null, null, true);
    }
}
