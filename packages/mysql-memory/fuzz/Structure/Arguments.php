<?php

declare(strict_types=1);

namespace Fuzz\Structure;

/**
 * A reflection argument vector retained between rejected constructor attempts.
 *
 * Entries follow runtime constructor metadata, including heterogeneous array
 * parameters. Keeping the vector separate preserves already sampled children
 * while another slot is resampled.
 */
final class Arguments
{
    /**
     * @param array<int, mixed> $values Generated values indexed by constructor position
     * @param string $failure The most recently rejected constructor invariant
     * @param int $repeats Consecutive trials rejected by that same invariant
     */
    public function __construct(public readonly array $values, public readonly string $failure = '', public readonly int $repeats = 0)
    {
    }
}
