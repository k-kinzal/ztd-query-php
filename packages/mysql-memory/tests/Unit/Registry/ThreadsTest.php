<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Registry\Threads;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Threads::class)]
#[Small]
final class ThreadsTest extends TestCase
{
    public function testNowReadsTheTimeSleepsHavePassed(): void
    {
        $threads = new Threads();
        $threads->pass(3600.0);

        self::assertGreaterThan(microtime(true) + 3500.0, $threads->now());
    }

    public function testPassIgnoresANegativeDuration(): void
    {
        $threads = new Threads();
        $threads->pass(-5.0);

        self::assertSame(0.0, $threads->passed);
    }

    public function testConnectRecordsTheSession(): void
    {
        $threads = new Threads();
        $threads->connect(3);

        self::assertSame([3 => true], $threads->connected);
    }

    public function testDisconnectReleasesTheLocksOfTheSession(): void
    {
        $threads = new Threads();
        $threads->connect(1);
        $threads->acquire('a', 1, false);
        $threads->acquire('b', 2, false);
        $threads->disconnect(1);

        self::assertSame([[], ['b' => [2, 1]]], [$threads->connected, $threads->locks]);
    }

    public function testOwnerAnswersNullForAFreeLock(): void
    {
        $threads = new Threads();
        $threads->acquire('a', 4, false);

        self::assertSame([4, null], [$threads->owner('a'), $threads->owner('b')]);
    }

    public function testAcquireCountsALockTakenAgain(): void
    {
        $threads = new Threads();

        self::assertSame([true, true, false], [$threads->acquire('a', 1, false), $threads->acquire('a', 1, false), $threads->acquire('a', 2, false)]);
        self::assertSame(['a' => [1, 2]], $threads->locks);
    }

    public function testAcquireReleasesTheOtherLockOfASingleLockSession(): void
    {
        $threads = new Threads();
        $threads->acquire('a', 1, true);
        $threads->acquire('a', 1, true);
        $threads->acquire('b', 2, true);
        $taken = $threads->acquire('b', 1, true);

        self::assertSame([false, ['b' => [2, 1]]], [$taken, $threads->locks]);
    }

    public function testReleaseAnswersWhetherTheSessionHeldTheLock(): void
    {
        $threads = new Threads();
        $threads->acquire('a', 1, false);
        $threads->acquire('a', 1, false);

        self::assertSame([0, 1, 1, null], [$threads->release('a', 2), $threads->release('a', 1), $threads->release('a', 1), $threads->release('a', 1)]);
    }

    public function testReleaseAllCountsEveryTimeALockWasTaken(): void
    {
        $threads = new Threads();
        $threads->acquire('a', 1, false);
        $threads->acquire('b', 1, false);
        $threads->acquire('a', 1, false);
        $threads->acquire('c', 2, false);

        self::assertSame([3, 0, ['c' => [2, 1]]], [$threads->releaseAll(1), $threads->releaseAll(1), $threads->locks]);
    }
}
