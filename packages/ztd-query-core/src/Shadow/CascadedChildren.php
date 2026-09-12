<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Schema\RowSet;
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
    private RowSet $rows;

    private RowSet $deleted;

    /** @var list<RowChange> */
    private array $updated = [];

    /**
     * @param array<int, Row> $rows The child rows as they stood
     */
    public function __construct(array $rows)
    {
        $this->rows = new RowSet($rows);
        $this->deleted = new RowSet();
    }

    /**
     * Answers the child rows as they stand.
     *
     * @return array<int, Row> The rows
     */
    public function rows(): array
    {
        return $this->rows->rows;
    }

    /**
     * Writes a row over the one in that position, and records the change.
     *
     * @param int $index Position of the row being written over
     * @param Row $row The row as it should now be
     */
    public function replace(int $index, array $row): void
    {
        $rows = $this->rows->rows;
        $this->updated[] = new RowChange($rows[$index], $row);
        $rows[$index] = $row;
        $this->rows = new RowSet($rows);
    }

    /**
     * Drops the rows in those positions, and records that they went.
     *
     * @param list<int> $indexes Positions of the rows that went
     */
    public function remove(array $indexes): void
    {
        $remaining = [];
        $deleted = $this->deleted->rows;
        foreach ($this->rows->rows as $index => $row) {
            if (in_array($index, $indexes, true)) {
                $deleted[] = $row;
                continue;
            }
            $remaining[] = $row;
        }
        $this->rows = new RowSet($remaining);
        $this->deleted = new RowSet($deleted);
    }

    /**
     * Answers the rows that went.
     *
     * @return list<Row> The rows
     */
    public function deleted(): array
    {
        return array_values($this->deleted->rows);
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
        return $this->deleted->rows === [] && $this->updated === [];
    }
}
