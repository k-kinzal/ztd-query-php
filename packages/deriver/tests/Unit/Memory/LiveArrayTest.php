<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use Deriver\Memory\LiveArray;
use Deriver\Memory\Location;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Memory\LiveArray
 */
#[CoversClass(LiveArray::class)]
#[UsesClass(Location::class)]
#[UsesClass(Term::class)]
#[Small]
final class LiveArrayTest extends TestCase
{
    public function testAdvanceUsesTheNextLiveBucket(): void
    {
        $cursor = new LiveArray(new Location('a'), [0,2]);
        $first = $cursor->advance();
        self::assertSame(0, $first->current);
        self::assertSame(2, $first->advance()->current);
        self::assertNull($first->advance()->advance()->current);
    }
    public function testChangedDeletesPendingBucketsAndAppendsReinsertedKeys(): void
    {
        $cursor = new LiveArray(new Location('a'), [1,2], 0);
        $before = Term::fromNative([1,2,3]);
        $removed = Term::fromNative([0 => 1,2 => 3]);
        $after = Term::fromNative([0 => 1,2 => 3,1 => 2]);
        $next = $cursor->changed($before, $removed, false)->changed($removed, $after, false);
        self::assertSame([2,1], $next->remaining);
        self::assertSame([0,2,1], $cursor->changed($before, $after, true)->remaining);
    }
}
