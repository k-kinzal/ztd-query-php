<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use MySqlMemory\Evaluation\ExactDecimal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExactDecimal::class)]
#[Small]
final class ExactDecimalTest extends TestCase
{
    public function testToStringKeepsTheUnroundedCalculationDigits(): void
    {
        self::assertSame('0.166666666', (string) new ExactDecimal('0.166666666', 4));
    }

    public function testTextRoundsOnlyTheWrittenResult(): void
    {
        $number = new ExactDecimal('-0.166666666', 4);
        self::assertSame('-0.1667', $number->text());
        self::assertSame('-0.166666666', $number->number);
        self::assertSame('0.0000', (new ExactDecimal('-0.000000001', 4))->text());
    }

    public function testRawPreservesScalarKindsAndUnwrapsExactNumbers(): void
    {
        self::assertSame([1, 1.5, '1.5', null, '1.555'], [ExactDecimal::raw(1), ExactDecimal::raw(1.5), ExactDecimal::raw('1.5'), ExactDecimal::raw(null), ExactDecimal::raw(new ExactDecimal('1.555', 2))]);
    }

}
