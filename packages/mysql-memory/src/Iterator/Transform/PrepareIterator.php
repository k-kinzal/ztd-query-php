<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Transform\Prepare;
use Override;

/**
 * Evaluates independent keyed subqueries before the first output row is requested.
 *
 * @visibility MySqlMemory
 */
final class PrepareIterator implements RowIterator
{
    /**
     * @param Prepare $path The preparation and row input
     * @param RowIterator $input The iterator of the row input
     */
    public function __construct(public readonly Prepare $path, public readonly RowIterator $input)
    {
    }

    /**
     * Prepares the conditions unless a constant outer table is absent, then starts the input.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $guard = $this->path->guard === null ? null : (new Builder())->build($this->path->guard);
        $guard?->init($frame);
        if ($guard === null || $guard->read() !== null) {
            foreach ($this->path->conditions as $condition) {
                $condition->prepare($frame);
            }
        }
        $this->input->init($frame);
    }

    /**
     * Answers the next unchanged input row.
     */
    #[Override]
    public function read(): ?array
    {
        return $this->input->read();
    }
}
