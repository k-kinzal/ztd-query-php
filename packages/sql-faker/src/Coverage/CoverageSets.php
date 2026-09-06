<?php

declare(strict_types=1);

namespace SqlFaker\Coverage;

/**
 * Maintains idempotent production sets and computes named measurements.
 *
 * @phpstan-type Measurement array{reachedIds: list<string>, emittedIds: list<string>, notReachedIds: list<string>, notEmittedIds: list<string>, reached: int, emitted: int, total: int, reachedRate: float, emittedRate: float}
 * @visibility root
 */
final class CoverageSets
{
    /**
     * @var array<string, true>
     */
    public array $reached = [];
    /**
     * @var array<string, true>
     */
    public array $emitted = [];

    /**
     * Unions history without copying counters or traces.
     */
    public function include(self $other): void
    {
        $this->reached += $other->reached;
        $this->emitted += $other->emitted;
    }

    /**
     * Computes rates against the root's full production inventory.
     *
     * @param list<string> $denominator
     * @return Measurement
     */
    public function measurement(array $denominator): array
    {
        $reached = array_keys($this->reached);
        $emitted = array_keys($this->emitted);
        sort($reached);
        sort($emitted);
        $total = count($denominator);
        $r = count(array_intersect($reached, $denominator));
        $e = count(array_intersect($emitted, $denominator));
        return ['reachedIds' => $reached, 'emittedIds' => $emitted,
            'notReachedIds' => array_values(array_diff($denominator, $reached)),
            'notEmittedIds' => array_values(array_diff($reached, $emitted)),
            'reached' => $r, 'emitted' => $e, 'total' => $total,
            'reachedRate' => $total === 0 ? 0.0 : $r / $total,
            'emittedRate' => $total === 0 ? 0.0 : $e / $total];
    }
}
