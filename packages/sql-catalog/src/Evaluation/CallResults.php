<?php

declare(strict_types=1);

namespace SqlCatalog\Evaluation;

/**
 * What following a call produced, remembered for the next caller that leads there.
 *
 * Following the same function with the same arguments reaches the same
 * statements and returns the same value, however many callers lead there.
 * Reading it once is what keeps a file whose functions call each other heavily
 * from being walked over and over.
 *
 * @visibility root
 */
final class CallResults
{
    /**
     * How many readings are remembered before the memory stops growing.
     */
    public const MAX_REMEMBERED = 4096;

    /**
     * @var array<string, Domain>
     */
    private array $results = [];

    /**
     * What tells one reading of a followed call apart from another.
     *
     * @param list<Domain> $arguments
     */
    public function keyFor(string $name, array $arguments): string
    {
        $signatures = [];
        foreach ($arguments as $argument) {
            $signatures[] = $argument->signature();
        }

        return $name . '|' . implode("\x1f", $signatures);
    }

    /**
     * What a reading produced last time, or null when it has not been read.
     */
    public function recall(string $key): ?Domain
    {
        return $this->results[$key] ?? null;
    }

    /**
     * Remembers what a reading produced, until the memory is full.
     */
    public function remember(string $key, Domain $result): void
    {
        if (count($this->results) < self::MAX_REMEMBERED) {
            $this->results[$key] = $result;
        }
    }

    /**
     * How many readings are remembered.
     */
    public function count(): int
    {
        return count($this->results);
    }
}
