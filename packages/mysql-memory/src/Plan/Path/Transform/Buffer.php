<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Plan\Path\AccessPath;
use Override;

/**
 * Completes an input before its stored rows are read in their original order.
 *
 * @visibility MySqlMemory
 */
final class Buffer implements AccessPath
{
    /**
     * @param AccessPath $input The rows retained within the current query block
     */
    public function __construct(public readonly AccessPath $input)
    {
    }

    /**
     * Answers the width of the stored input.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width();
    }
}
