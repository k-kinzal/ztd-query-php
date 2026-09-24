<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ErrorCode;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ErrorCode::class)]
final class ErrorCodeTest extends TestCase
{
    #[TestWith(['1062', 1062])]
    #[TestWith(['0x10', 16])]
    #[TestWith(['12.9', 12])]
    #[TestWith(['7e3', 7])]
    public function testNumberReadsTheServerErrorNumber(string $spelling, int $number): void
    {
        self::assertSame($number, (new ErrorCode($spelling))->number());
    }

    #[TestWith(['0'])]
    #[TestWith(['0x0'])]
    #[TestWith(['0.5'])]
    #[TestWith(['abc'])]
    #[TestWith(['x12'])]
    #[TestWith(['12x'])]
    #[TestWith(["12\n"])]
    public function testRejectsZeroAndNonNumbers(string $spelling): void
    {
        $this->expectException(InvalidStructure::class);
        new ErrorCode($spelling);
    }
}
