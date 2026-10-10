<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Source;

use MySqlMemory\Plan\Path\Source\SingleRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SingleRow::class)]
#[Small]
final class SingleRowTest extends TestCase
{
    public function testWidthIsZero(): void
    {
        self::assertSame(0, (new SingleRow())->width());
    }
}
