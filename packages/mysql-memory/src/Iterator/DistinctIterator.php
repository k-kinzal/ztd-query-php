<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\Distinct;
use MySqlMemory\Value\Order;

/**
 * Passes the first row of each set of rows with equal leading values.
 *
 * @visibility MySqlMemory
 */
final class DistinctIterator implements RowIterator
{
    /**
     * @var array<string, true>
     */
    private array $seen = [];

    /**
     * @param Distinct $path The path executed
     * @param RowIterator $input The iterator of the input
     */
    public function __construct(public readonly Distinct $path, public readonly RowIterator $input)
    {
    }

    /**
     * Starts the input and forgets the rows seen.
     */
    #[\Override]
    public function init(Frame $frame): void
    {
        $this->seen = [];
        $this->input->init($frame);
    }

    /**
     * Answers the next row not seen before.
     */
    #[\Override]
    public function read(): ?array
    {
        while (($row = $this->input->read()) !== null) {
            $key = '';
            foreach ($this->path->domains as $position => $domain) {
                $key .= Order::key($row[$position], $domain) . "\0";
            }
            if (!isset($this->seen[$key])) {
                $this->seen[$key] = true;

                return $row;
            }
        }

        return null;
    }
}
