<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Combine;

use MySqlMemory\Plan\Path\Combine\UnionStream;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnionStream::class)]
#[Small]
final class UnionStreamTest extends TestCase
{
    public function testWidthDiscardsOperandColumnsOutsideTheCombinedOutput(): void
    {
        self::assertSame(2, (new UnionStream(new ZeroRows(3), new ZeroRows(4), 2, true))->width());
    }
}
