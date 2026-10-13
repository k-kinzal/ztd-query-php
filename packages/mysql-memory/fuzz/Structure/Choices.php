<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use InvalidArgumentException;

/**
 * Deterministic choices, independent of the process random state.
 */
final class Choices
{
    private int $offset = 0;

    /**
     * @param string $seed The replay seed
     */
    public function __construct(public readonly string $seed)
    {
    }

    /**
     * Samples an inclusive integer interval.
     *
     * @throws InvalidArgumentException When the interval is empty
     */
    public function integer(int $minimum, int $maximum): int
    {
        if ($minimum > $maximum) {
            throw new InvalidArgumentException('An integer interval must be nonempty.');
        }
        $draw = intval(substr(hash('sha256', $this->seed . ':' . $this->offset++), 0, 7), 16);

        return $minimum + $draw % ($maximum - $minimum + 1);
    }

    /**
     * @template T
     * @param list<T> $values
     * @return T
     * @throws Exhausted When there are no alternatives
     */
    public function one(array $values): mixed
    {
        if ($values === []) {
            throw new Exhausted('No alternative has a finite construction at this depth.');
        }

        return $values[$this->integer(0, count($values) - 1)];
    }
}
