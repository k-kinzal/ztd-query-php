<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Buffer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Buffer::class)]
#[Small]
final class BufferTest extends TestCase
{
    public function testWidthPreservesEveryInputField(): void
    {
        self::assertSame(3, (new Buffer(new ZeroRows(3)))->width());
    }
}
