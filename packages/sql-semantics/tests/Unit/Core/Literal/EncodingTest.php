<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Literal\DecodingException;
use SqlSemantics\Core\Literal\Encoding;

#[CoversClass(Encoding::class)]
#[Medium]
final class EncodingTest extends TestCase
{
    public function testHexRetainsBytesAndPadsAnOddDigit(): void
    {
        self::assertSame("\0\xff", Encoding::hex('00ff'));
        self::assertSame("\x0a", Encoding::hex('a'));
        self::assertSame('', Encoding::hex(''));
        $this->expectException(DecodingException::class);
        Encoding::hex('xz');
    }
    public function testBitsPreservesWidthAndRejectsInvalidDigits(): void
    {
        self::assertSame('00000001', Encoding::bits('01', true));
        self::assertSame('001', Encoding::bits('001'));
        self::assertSame('', Encoding::bits('', true));
        $this->expectException(DecodingException::class);
        Encoding::bits('012');
    }
    public function testBytesPacksWholeAndPartialOctets(): void
    {
        self::assertSame("\1\0", Encoding::bytes('100000000'));
        self::assertSame('', Encoding::bytes(''));
    }
    #[TestWith(['0x10000000000000000', '18446744073709551616'])]
    #[TestWith(['0o777', '511'])]
    #[TestWith(['0b1_001', '9'])]
    #[TestWith(['0x000', '0'])]
    #[TestWith(['1_000.20', '1000.20'])]
    public function testNumberConvertsWithoutFloatRounding(string $text, string $expected): void
    {
        self::assertSame($expected, Encoding::number($text));
    }


    public function testSuccessorDoesNotOverflowMachineIntegers(): void
    {
        self::assertSame('100000000000000000000', Encoding::successor('99999999999999999999'));
        self::assertSame('42', Encoding::successor('41'));
        $this->expectException(DecodingException::class);
        Encoding::successor('1.2');
    }

}
