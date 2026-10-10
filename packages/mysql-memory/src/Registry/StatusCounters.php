<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * Cumulative server counters and the independently resettable counters of each client.
 *
 * A session reset or disconnect does not subtract its contribution to global totals.
 * FLUSH STATUS clears session counters and selected global counters; command and traffic totals survive.
 * RESTART clears all totals.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flush.html.
 *
 * @visibility MySqlMemory
 */
final class StatusCounters
{
    /** @var array<string, int> */
    private array $global = [];

    /** @var array<int, array<string, int>> */
    private array $sessions = [];

    /**
     * The last FLUSH STATUS instant, or null before the first flush.
     */
    public ?float $flushedAt = null;

    /**
     * The server generation; only a restart advances it, so late traffic from old clients can be ignored.
     */
    public int $generation = 0;

    /**
     * Adds an event count or byte amount globally and, when supplied, for its connection.
     */
    public function add(string $name, ?int $connection = null, int $amount = 1): void
    {
        $this->global[$name] = ($this->global[$name] ?? 0) + $amount;
        if ($connection !== null) {
            $this->sessions[$connection][$name] = ($this->sessions[$connection][$name] ?? 0) + $amount;
        }
    }

    /**
     * Reads a global or session total, initially zero.
     */
    public function read(string $name, ?int $connection = null): int
    {
        return $connection === null ? ($this->global[$name] ?? 0) : ($this->sessions[$connection][$name] ?? 0);
    }

    /**
     * Forgets one session's counts, or all sessions when no connection is supplied.
     * Global contributions remain in either case.
     */
    public function clear(?int $connection = null): void
    {
        if ($connection === null) {
            $this->sessions = [];

            return;
        }
        unset($this->sessions[$connection]);
    }

    /**
     * Clears a resettable server counter without affecting other totals or session records.
     */
    public function clearGlobal(string $name): void
    {
        unset($this->global[$name]);
    }

    /**
     * Resets all counts when the server restarts.
     */
    public function reset(): void
    {
        ++$this->generation;
        $this->global = [];
        $this->sessions = [];
        $this->flushedAt = null;
    }
}
