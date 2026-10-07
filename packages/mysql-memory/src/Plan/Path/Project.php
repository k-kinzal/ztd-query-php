<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

use MySqlMemory\Evaluation\Evaluable;
use Override;

/**
 * Computes the output values of each row: the select list, and the keys sorted by after it.
 *
 * @visibility MySqlMemory
 */
final class Project implements AccessPath
{
    /**
     * @param AccessPath $input The rows read
     * @param list<Evaluable> $expressions The expressions, evaluated over each row
     */
    public function __construct(public readonly AccessPath $input, public readonly array $expressions)
    {
    }

    /**
     * Answers the number of expressions.
     */
    #[Override]
    public function width(): int
    {
        return count($this->expressions);
    }
}
