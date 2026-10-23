<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Lock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Lock::class)]
#[Small]
final class LockTest extends TestCase
{
    public function testWidthIsTheWidthOfTheInput(): void
    {
        self::assertSame(3, (new Lock(new ZeroRows(3), null, []))->width());
    }
}
