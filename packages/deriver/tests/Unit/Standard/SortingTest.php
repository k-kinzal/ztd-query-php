<?php

declare(strict_types=1);

namespace Tests\Unit\Standard;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Standard\Sorting
 */
#[CoversClass(\Deriver\Standard\Sorting::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class SortingTest extends TestCase
{
    public function testApplyReindexesNumericValues(): void
    {
        self::assertSame(['2', 11, 30], (new \Deriver\Standard\Sorting())->apply([\Deriver\Value\Term::fromNative(['z' => 30, 'a' => '2', 7 => 11]), \Deriver\Value\Term::constant(1)])->native());
    }
    public function testApplyRetainsUnsupportedLocaleFlags(): void
    {
        self::assertSame('opaque', (new \Deriver\Standard\Sorting())->apply([\Deriver\Value\Term::fromNative(['z', 'a']), \Deriver\Value\Term::constant(5)])->kind);
    }
}
