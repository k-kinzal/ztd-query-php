<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use MySqlMemory\Result\Interleaving;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Interleaving::class)]
#[Small]
final class InterleavingTest extends TestCase
{
    public function testCollectRetainsRepeatedEmissionsAtTheSameOffsetAndLaterRows(): void
    {
        $first = new ResultSet([], [['first']]);
        $second = new ResultSet([], [['second']]);
        $pending = [[$first], [$second], [], [$first, $second]];
        $emissions = new Interleaving(static function () use (&$pending): array {
            return array_shift($pending) ?? [];
        });
        $emissions->collect(0);
        $emissions->collect(0);
        $emissions->collect(1);
        $emissions->collect(2);

        self::assertSame([0 => [$first, $second], 2 => [$first, $second]], $emissions->results);
    }

    public function testCollectWithoutAnEmitterLeavesTheResponseUninterrupted(): void
    {
        $emissions = new Interleaving();
        $emissions->collect(0);

        self::assertSame([], $emissions->results);
    }
}
