<?php

declare(strict_types=1);

namespace Tests\Unit\Concurrency;

use MySqlMemory\Concurrency\LockMode;
use MySqlMemory\Concurrency\RowLocks;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RowLocks::class)]
#[Small]
final class RowLocksTest extends TestCase
{
    public function testBlockersAnswersTheTransactionsHoldingAnIncompatibleLock(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $locks = new RowLocks();
        $locks->grant($table, 1, 7, LockMode::Shared);
        $locks->grant($table, 1, 8, LockMode::Shared);

        self::assertSame([[], [7, 8], [8]], [$locks->blockers($table, 1, 9, LockMode::Shared), $locks->blockers($table, 1, 9, LockMode::Exclusive), $locks->blockers($table, 1, 7, LockMode::Exclusive)]);
    }

    public function testHoldsTellsWhetherALockIncludesAMode(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $locks = new RowLocks();
        $locks->grant($table, 1, 7, LockMode::Shared);

        self::assertSame([true, false, false], [$locks->holds($table, 1, 7, LockMode::Shared), $locks->holds($table, 1, 7, LockMode::Exclusive), $locks->holds($table, 2, 7, LockMode::Shared)]);
    }

    public function testGrantKeepsAStrongerLock(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $locks = new RowLocks();
        $locks->grant($table, 1, 7, LockMode::Exclusive);
        $locks->grant($table, 1, 7, LockMode::Shared);

        self::assertSame([LockMode::Exclusive], array_values($locks->owned[7][spl_object_id($table)]));
    }

    public function testReleaseFreesEveryLockOfATransaction(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $locks = new RowLocks();
        $locks->grant($table, 1, 7, LockMode::Exclusive);
        $locks->grant($table, 2, 7, LockMode::Shared);
        $locks->waits[7] = [8];
        $locks->release(7);

        self::assertSame([[], [], [], []], [$locks->held, $locks->owned, $locks->tables, $locks->waits]);
    }

    public function testGroupsCountsATableLockAndARowLockGroupForEachTableAndModeAndTheWait(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT); CREATE TABLE d.u (a INT)');
        $t = $instance->dictionary->table('d', 't');
        $u = $instance->dictionary->table('d', 'u');
        self::assertNotNull($t);
        self::assertNotNull($u);
        $locks = new RowLocks();
        $locks->grant($t, 1, 7, LockMode::Exclusive);
        $locks->grant($t, 2, 7, LockMode::Exclusive);
        $locks->grant($u, 1, 7, LockMode::Shared);
        $locks->waits[7] = [8];

        self::assertSame([5, 0], [$locks->groups(7), $locks->groups(8)]);
    }

    public function testCycleAnswersTheTransactionsOfTheCycleARequestCloses(): void
    {
        $locks = new RowLocks();
        $locks->waits[8] = [9];
        $locks->waits[9] = [7];

        self::assertSame([[7, 8, 9], [], []], [$locks->cycle(7, [8]), $locks->cycle(7, [10]), $locks->cycle(10, [8])]);
    }
}
