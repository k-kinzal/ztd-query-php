<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Row\RowChange;

/**
 * The rows of one child table as a cascade leaves them.
 *
 * Following a constraint reads the child rows once and then rewrites some and
 * drops others, and the caller needs both the rows to write back and an
 * account of what happened to them. Keeping the two together is what makes
 * the account impossible to disagree with the rows.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class CascadedChildren
{
    /** @var list<Row> */
    private array $rows;

    /** @var list<Row> */
    private array $deleted = [];

    /** @var list<RowChange> */
    private array $updated = [];

    /**
     * @param list<Row> $rows The child rows as they stood
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * Answers the child rows as they stand.
     *
     * @return list<Row> The rows
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * Writes a row over the one in that position, and records the change.
     *
     * @param int $index Position of the row being written over
     * @param Row $row The row as it should now be
     */
    public function replace(int $index, array $row): void
    {
        $this->updated[] = new RowChange($this->rows[$index], $row);
        $this->rows[$index] = $row;
    }

    /**
     * Drops the rows in those positions, and records that they went.
     *
     * @param list<int> $indexes Positions of the rows that went
     */
    public function remove(array $indexes): void
    {
        $remaining = [];
        foreach ($this->rows as $index => $row) {
            if (in_array($index, $indexes, true)) {
                $this->deleted[] = $row;
                continue;
            }
            $remaining[] = $row;
        }
        $this->rows = $remaining;
    }

    /**
     * Answers the rows that went.
     *
     * @return list<Row> The rows
     */
    public function deleted(): array
    {
        return $this->deleted;
    }

    /**
     * Answers what happened to the rows that stayed.
     *
     * @return list<RowChange> The changes
     */
    public function updated(): array
    {
        return $this->updated;
    }

    /**
     * Reports whether the cascade reached this table at all.
     *
     * @return bool True when nothing went and nothing changed
     */
    public function areUnchanged(): bool
    {
        return $this->deleted === [] && $this->updated === [];
    }
}
