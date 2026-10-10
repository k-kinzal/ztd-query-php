<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use Override;

/**
 * The bounds of INTERVAL read once for the statement, searched by halves.
 *
 * The search narrows the range of bounds to the last one N reaches, taking the upper half
 * whenever the bound in its middle is at most N, as if the bounds were in ascending order; it
 * answers 0 when N is below the bound it ends on, else the position of that bound. Bounds out of
 * order answer as the server answers them (verified on a live 8.4 server).
 *
 * @visibility MySqlMemory
 */
final class Ranges implements Evaluable
{
    /**
     * @param list<string|float> $bounds The bounds, as decimal texts or doubles
     * @param bool $exact Whether the bounds are decimal texts
     * @param Domain $domain The domain of the first bound
     */
    public function __construct(public readonly array $bounds, public readonly bool $exact, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the first bound.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Answers the first bound.
     */
    #[Override]
    public function evaluate(Frame $frame): float|string|null
    {
        return $this->bounds[0] ?? null;
    }

    /**
     * Answers how many bounds a number reaches, searching by halves.
     */
    public function search(string|float $number): int
    {
        $start = 0;
        $end = count($this->bounds) - 1;
        while ($start !== $end) {
            $middle = intdiv($start + $end + 1, 2);
            if ($this->compare($this->bounds[$middle], $number) <= 0) {
                $start = $middle;
            } else {
                $end = $middle - 1;
            }
        }

        return $this->compare($number, $this->bounds[$start]) < 0 ? 0 : $start + 1;
    }

    /**
     * Compares two values of the bounds' kind.
     */
    public function compare(string|float $left, string|float $right): int
    {
        return $this->exact ? Decimal::compare((string) $left, (string) $right) : (float) $left <=> (float) $right;
    }
}
