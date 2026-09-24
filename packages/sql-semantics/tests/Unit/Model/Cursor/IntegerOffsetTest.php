<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Cursor\IntegerOffset;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(IntegerOffset::class)]
final class IntegerOffsetTest extends TestCase
{
    #[TestWith(['0'])]
    #[TestWith(['-2'])]
    #[TestWith(['+7'])]
    #[TestWith(['99999999999999999999999'])]
    #[TestWith(['1_000'])]
    #[TestWith(['0x1F'])]
    #[TestWith(['0o17'])]
    #[TestWith(['0b1010'])]
    public function testKeepsTheExactIntegerSpelling(string $text): void
    {
        self::assertSame($text, (new IntegerOffset($text))->text);
    }

    #[TestWith([''])]
    #[TestWith(['1.5'])]
    #[TestWith(['1e3'])]
    #[TestWith(['--1'])]
    #[TestWith([' 1'])]
    #[TestWith(['abc'])]
    #[TestWith(['0x'])]
    #[TestWith(['1_'])]
    public function testRejectsAnythingButOneSignedIntegerConstant(string $text): void
    {
        $this->expectException(InvalidStructure::class);
        new IntegerOffset($text);
    }
}
