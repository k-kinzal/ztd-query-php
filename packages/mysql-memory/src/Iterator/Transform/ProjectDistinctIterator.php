<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Transform\ProjectDistinct;
use MySqlMemory\Value\Order;
use Override;

/**
 * Computes comparison fields once, then deferred expressions only for retained rows.
 *
 * @visibility MySqlMemory
 */
final class ProjectDistinctIterator implements RowIterator
{
    private Frame $frame;

    /**
     * @var array<string, true>
     */
    private array $seen = [];

    /**
     * @param ProjectDistinct $path The selected fields and deferred projection
     * @param RowIterator $input The unprojected input rows
     */
    public function __construct(public readonly ProjectDistinct $path, public readonly RowIterator $input)
    {
    }

    /**
     * Starts the input and clears the keys from the previous execution.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $this->seen = [];
        $this->input->init($frame);
    }

    /**
     * Answers the next retained projection without evaluating a discarded row's parents.
     */
    #[Override]
    public function read(): ?array
    {
        while (($row = $this->input->read()) !== null) {
            $this->frame->row = $row;
            $values = [];
            $key = '';
            foreach ($this->path->keys as $position => $domain) {
                $values[$position] = $this->path->expressions[$position]->evaluate($this->frame);
                $key .= Order::key($values[$position], $domain, $this->path->hashed) . "\0";
            }
            if ($this->path->keys !== [] && isset($this->seen[$key])) {
                continue;
            }
            $this->seen[$key] = true;
            $projected = [];
            foreach ($this->path->expressions as $position => $expression) {
                $projected[] = array_key_exists($position, $values) ? $values[$position] : $expression->evaluate($this->frame);
            }

            return $projected;
        }

        return null;
    }
}
