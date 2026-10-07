<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Source;

use MySqlMemory\Plan\Path\Source\ZeroRows;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ZeroRows::class)]
#[Small]
final class ZeroRowsTest extends TestCase
{
    public function testWidthIsTheWidthOfTheRowsNotProduced(): void
    {
        self::assertSame(3, (new ZeroRows(3))->width());
    }
}
