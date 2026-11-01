<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Prepare;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Prepare::class)]
#[Small]
final class PrepareTest extends TestCase
{
    public function testWidthPreservesTheInputLayout(): void
    {
        self::assertSame(4, (new Prepare(new ZeroRows(4), []))->width());
    }
}
