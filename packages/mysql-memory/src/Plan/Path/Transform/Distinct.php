<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * Passes the first row of each set of rows equal in their leading values.
 *
 * Values are equal as the server groups them: strings by their collation, NULL equal to NULL.
 *
 * @visibility MySqlMemory
 */
final class Distinct implements AccessPath
{
    /**
     * @param AccessPath $input The rows
     * @param list<Domain> $domains The domains of the leading values compared
     */
    public function __construct(public readonly AccessPath $input, public readonly array $domains)
    {
    }

    /**
     * Answers the width of the input.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width();
    }
}
