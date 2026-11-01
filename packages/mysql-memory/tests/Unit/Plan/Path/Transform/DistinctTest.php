<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Distinct;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Distinct::class)]
#[Small]
final class DistinctTest extends TestCase
{
    public function testWidthIsTheWidthOfTheInputNotOfTheComparedValues(): void
    {
        self::assertSame(3, (new Distinct(new ZeroRows(3), [Domain::integer()]))->width());
    }
}
