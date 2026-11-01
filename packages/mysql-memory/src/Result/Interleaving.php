<?php

declare(strict_types=1);

namespace MySqlMemory\Result;

use Closure;

/**
 * Records nested result emissions at the caller row whose evaluation produced them.
 *
 * A skipped caller row can still emit nested results. Multiple evaluations at the
 * same output offset retain their emission order, including a final failing row.
 *
 * @visibility MySqlMemory
 */
final class Interleaving
{
    /**
     * @var array<int, list<ResultSet>> The emissions preceding each caller row offset
     */
    public array $results = [];

    /**
     * @param Closure(): list<ResultSet>|null $receive Takes emissions since the preceding observation
     */
    public function __construct(public readonly ?Closure $receive = null)
    {
    }

    /**
     * Collects emissions at an output offset without replacing earlier emissions there.
     */
    public function collect(int $offset): void
    {
        foreach ($this->receive === null ? [] : ($this->receive)() as $result) {
            $this->results[$offset][] = $result;
        }
    }
}
