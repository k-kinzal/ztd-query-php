<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;

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

    public function testToStringKeepsTheSignPointAndExponent(): void
    {
        $value = new RealLiteral('', '0', '+0', true);

        self::assertSame('-.0e+0', $value->toString());
    }

    public function testRejectsAnIntegerInThePointFreeRealRole(): void
    {
        $this->expectExceptionMessage('signed INTEGER boundary');

        new RealLiteral('9223372036854775808', null, null, true);
    }

    public function testPayloadBytesCountsTheVariableParts(): void
    {
        self::assertSame(7, (new RealLiteral('09', '01', '-14'))->payloadBytes());
        self::assertSame(1, (new RealLiteral('4', ''))->payloadBytes());
    }
}
