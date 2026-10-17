<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Limit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Limit::class)]
#[Small]
final class LimitTest extends TestCase
{
    public function testWidthIsTheWidthOfTheInput(): void
    {
        $limit = new Limit(new ZeroRows(2), null);

        self::assertSame(2, $limit->width());
        self::assertSame(0, $limit->offset);
    }
}
