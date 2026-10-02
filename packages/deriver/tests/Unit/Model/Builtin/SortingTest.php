<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Model\Builtin\Sorting;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Builtin\Sorting
 */
#[CoversClass(Sorting::class)]
#[UsesClass(Term::class)]
#[UsesClass(\Deriver\Value\FloatConversion::class)]
#[Small]
final class SortingTest extends TestCase
{
    public function testApplyReindexesNumericValues(): void
    {
        self::assertSame(['2', 11, 30], (new Sorting())->apply([Term::fromNative(['z' => 30, 'a' => '2', 7 => 11]), Term::constant(1)])->native());
    }
    public function testApplyRetainsUnsupportedLocaleFlags(): void
    {
        self::assertSame('opaque', (new Sorting())->apply([Term::fromNative(['z', 'a']), Term::constant(5)])->kind);
    }

    public function testApplyConvertsFloatsForStringSortingUnderTheCapturedPrecision(): void
    {
        $values = [Term::fromNative(['0.3 x', 0.1 + 0.2]), Term::constant(2)];
        self::assertSame([0.1 + 0.2, '0.3 x'], (new Sorting(14))->apply($values)->native());
        self::assertSame(['0.3 x', 0.1 + 0.2], (new Sorting(-1))->apply($values)->native());
    }
}
