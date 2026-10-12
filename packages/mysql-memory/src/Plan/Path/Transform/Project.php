<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Plan\Path\AccessPath;
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
     * @param bool $append Whether to preserve input columns before the computed values
     * @param int|null $retainAt The input position whose positive marker repeats the previous computed values
     * @param bool $publish Whether expressions read the latest computed fields from the frame
     * @param list<int> $order The evaluation order of expression positions; empty uses their written order
     */
    public function __construct(public readonly AccessPath $input, public readonly array $expressions, public readonly bool $append = false, public readonly ?int $retainAt = null, public readonly bool $publish = false, public readonly array $order = [])
    {
    }

    /**
     * Answers the number of computed values, including preserved input columns.
     */
    #[Override]
    public function width(): int
    {
        return ($this->append ? $this->input->width() : 0) + count($this->expressions);
    }
}
