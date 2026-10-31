<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * Removes duplicates using selected output fields before computing deferred expressions.
 * An empty key set keeps every row because no stored output participates in comparison.
 *
 * @visibility MySqlMemory
 */
final class ProjectDistinct implements AccessPath
{
    /**
     * @param AccessPath $input The rows before projection
     * @param list<Evaluable> $expressions The complete projection, including ordering values
     * @param array<int, Domain> $keys The output positions and domains used for duplicate removal
     * @param bool $hashed Whether comparison uses temporary collation hash keys
     */
    public function __construct(public readonly AccessPath $input, public readonly array $expressions, public readonly array $keys, public readonly bool $hashed = false)
    {
    }

    /**
     * Answers the width after both stored and deferred expressions are projected.
     */
    #[Override]
    public function width(): int
    {
        return count($this->expressions);
    }
}
