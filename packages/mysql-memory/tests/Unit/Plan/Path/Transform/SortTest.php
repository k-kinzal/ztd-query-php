<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sort::class)]
#[Small]
final class SortTest extends TestCase
{
    public function testWidthIsTheWidthOfTheInput(): void
    {
        self::assertSame(2, (new Sort(new ZeroRows(2), [[1, Domain::integer(), true]]))->width());
    }
}
