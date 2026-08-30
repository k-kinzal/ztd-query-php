<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Row;

use ZtdQuery\Schema\TableDefinition;

/**
 * Which rows of a shadow before are which rows of the shadow after.
 *
 * Pairing rows off is done in passes — the pairs an UPDATE tells us about
 * first, then whatever the key can still match — and each pass has to know
 * what the ones before it already took, or a row would be paired twice.
 * Keeping the positions and the changes together is what makes that so.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class RowPairing
{
    /** @var list<int> */
    private array $before = [];

    /** @var list<int> */
    private array $after = [];

    /** @var list<RowChange> */
    private array $changes = [];

    /**
     * Pairs two rows off, and records the change where they differ.
     *
     * @param int $beforeIndex Where the row was
     * @param int $afterIndex Where it became
     * @param Row $beforeRow The row as it was
     * @param Row $afterRow The row as it became
     */
    public function pair(int $beforeIndex, int $afterIndex, array $beforeRow, array $afterRow): void
    {
        $this->before[] = $beforeIndex;
        $this->after[] = $afterIndex;
        if ($beforeRow !== $afterRow) {
            $this->changes[] = new RowChange($beforeRow, $afterRow);
        }
    }

    /**
     * Answers the positions in the shadow before that were paired off.
     *
     * @return list<int> The positions
     */
    public function beforePositions(): array
    {
        return $this->before;
    }

    /**
     * Answers the positions in the shadow after that were paired off.
     *
     * @return list<int> The positions
     */
    public function afterPositions(): array
    {
        return $this->after;
    }

    /**
     * Answers what happened to the rows that were paired off and differ.
     *
     * @return list<RowChange> The changes
     */
    public function changes(): array
    {
        return $this->changes;
    }
}
