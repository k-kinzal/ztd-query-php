<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * Cumulative server counters and the independently resettable counters of each client.
 *
 * A session reset or disconnect does not subtract its contribution to global totals.
 * FLUSH STATUS clears only that connection's statement counters. RESTART clears all totals.
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
     * Counts an event globally and, when supplied, for its connection.
     */
    public function add(string $name, ?int $connection = null): void
    {
        $this->global[$name] = ($this->global[$name] ?? 0) + 1;
        if ($connection !== null) {
            $this->sessions[$connection][$name] = ($this->sessions[$connection][$name] ?? 0) + 1;
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
     * Forgets a session's counts, retaining all its global contributions.
     */
    public function clear(int $connection): void
    {
        unset($this->sessions[$connection]);
    }

    /**
     * Resets all counts when the server restarts.
     */
    public function reset(): void
    {
        $this->global = [];
        $this->sessions = [];
        $this->flushedAt = null;
    }
}
