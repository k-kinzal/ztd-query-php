<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

/**
 * The rows of one stored table, each under a row number, and its AUTO_INCREMENT counter.
 *
 * A row is the list of its column values in declared order, held as the column domains say.
 * Row numbers grow with each insert and are never reused.
 *
 * @visibility MySqlMemory
 */
final class Heap
{
    /**
     * @param array<int, list<int|float|string|null>> $rows The rows by row number
     * @param int $nextRow The number of the next inserted row
     * @param int $autoIncrement The next AUTO_INCREMENT value, held as an unsigned int
     */
    public function __construct(public array $rows = [], public int $nextRow = 1, public int $autoIncrement = 1)
    {
    }

    /**
     * Adds a row and answers its number.
     *
     * @param list<int|float|string|null> $row
     */
    public function insert(array $row): int
    {
        $number = $this->nextRow++;
        $this->rows[$number] = $row;

        return $number;
    }

    /**
     * Replaces the values of a row.
     *
     * @param list<int|float|string|null> $row
     */
    public function update(int $number, array $row): void
    {
        $this->rows[$number] = $row;
    }

    /**
     * Removes a row.
     */
    public function delete(int $number): void
    {
        unset($this->rows[$number]);
    }

    /**
     * Answers a copy that later changes to this data do not reach.
     */
    public function copy(): self
    {
        return new self($this->rows, $this->nextRow, $this->autoIncrement);
    }
}
