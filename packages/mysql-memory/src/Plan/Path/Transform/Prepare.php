<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Evaluation\Subquery\RowMembership;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Source\KeyLookup;
use Override;

/**
 * Prepares independent constant-key predicates before consuming the block's rows.
 *
 * @visibility MySqlMemory
 */
final class Prepare implements AccessPath
{
    /**
     * @param AccessPath $input The block's output rows
     * @param list<RowMembership> $conditions Predicates prepared once per statement
     * @param bool $early Whether preparation precedes a surrounding LIMIT, as in legacy releases
     * @param KeyLookup|null $guard A constant outer table whose missing row eliminates preparation
     */
    public function __construct(public readonly AccessPath $input, public readonly array $conditions, public readonly bool $early = false, public readonly ?KeyLookup $guard = null)
    {
    }

    /**
     * Answers the unchanged input width.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width();
    }
}
