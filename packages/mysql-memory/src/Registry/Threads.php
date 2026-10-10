<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * The client threads of the server: the sessions connected, the user-level locks they hold, and the time their sleeps and lock waits have passed.
 *
 * A user-level lock is named; GET_LOCK() takes it for one session at a time, and the session
 * that holds it can take it again, each time counting once more until RELEASE_LOCK() has been
 * called as often. MySQL 5.6 holds one lock per session: taking another releases it, and taking
 * it again does not count. Every lock of a session is released when the session ends.
 *
 * The emulator runs one statement at a time, so a session never waits for another to release a
 * lock, and it does not stall the caller while SLEEP() runs or GET_LOCK() waits: the time those
 * take is added to the clock of the server, which every session reads, so that SYSDATE(), NOW()
 * of a later statement and the clocks that follow see it pass.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html,
 * https://dev.mysql.com/doc/refman/5.6/en/locking-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Threads
{
    /**
     * @var array<int, true> The ids of the sessions connected, as keys
     */
    public array $connected = [];

    /**
     * @var array<string, array{int, int}> The connection id of the session holding each lock and the number of times it took it, by the key of the lock name
     */
    public array $locks = [];

    /**
     * The seconds the sleeps and lock waits of every session have passed.
     */
    public float $passed = 0.0;

    /**
     * The last statement sequence number allocated across all client sessions.
     */
    public int $statements = 0;

    /**
     * Answers the current time of the server, as a Unix time with microseconds.
     */
    public function now(): float
    {
        return microtime(true) + $this->passed;
    }

    /**
     * Lets time pass, as a sleep or a lock wait does.
     */
    public function pass(float $seconds): void
    {
        $this->passed += max(0.0, $seconds);
    }

    /**
     * Records a session as connected.
     */
    public function connect(int $connection): void
    {
        $this->connected[$connection] = true;
    }

    /**
     * Records a session as ended and releases every lock it holds.
     */
    public function disconnect(int $connection): void
    {
        unset($this->connected[$connection]);
        $this->releaseAll($connection);
    }

    /**
     * Answers the connection id of the session holding a lock, or null when the lock is free.
     */
    public function owner(string $key): ?int
    {
        return $this->locks[$key][0] ?? null;
    }

    /**
     * Takes a lock for a session and tells whether it holds it now; another session holding it keeps it.
     *
     * @param bool $single Whether a session holds one lock at most, as in MySQL 5.6, so that taking one releases the other
     */
    public function acquire(string $key, int $connection, bool $single): bool
    {
        if ($single) {
            foreach ($this->locks as $held => [$owner]) {
                if ($owner === $connection && $held !== $key) {
                    unset($this->locks[$held]);
                }
            }
        }
        $owner = $this->owner($key);
        if ($owner !== null && $owner !== $connection) {
            return false;
        }
        $this->locks[$key] = [$connection, $single ? 1 : ($this->locks[$key][1] ?? 0) + 1];

        return true;
    }

    /**
     * Releases a lock once: 1 when the session held it, 0 when another session holds it, null when it is free.
     */
    public function release(string $key, int $connection): ?int
    {
        $owner = $this->owner($key);
        if ($owner === null) {
            return null;
        }
        if ($owner !== $connection) {
            return 0;
        }
        $count = $this->locks[$key][1] - 1;
        if ($count === 0) {
            unset($this->locks[$key]);
        } else {
            $this->locks[$key] = [$connection, $count];
        }

        return 1;
    }

    /**
     * Releases every lock a session holds and answers how many times it had taken them.
     */
    public function releaseAll(int $connection): int
    {
        $released = 0;
        foreach ($this->locks as $key => [$owner, $count]) {
            if ($owner === $connection) {
                $released += $count;
                unset($this->locks[$key]);
            }
        }

        return $released;
    }
}
