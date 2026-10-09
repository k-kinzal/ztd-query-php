<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Session\Locator;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Locates the names of an UPDATE, an INSERT or a DELETE in the order the server resolves them.
 *
 * An UPDATE resolves WHERE, every assigned column, every value, then ORDER BY; an INSERT
 * resolves its column list or the columns of SET, the rows or the values of SET, then the
 * columns and the values of ON DUPLICATE KEY UPDATE, except that an INSERT ... SELECT resolves
 * the columns of ON DUPLICATE KEY UPDATE before the query (verified on a live 8.4 server). MySQL
 * 5.6 and 5.7 resolve the query of an INSERT ... SELECT before its column list, and 5.6 resolves
 * the first row, or the values of SET, before the columns (verified on live 5.6.51 and 5.7.44
 * servers).
 *
 * @visibility MySqlMemory
 */
final class WriteOrder
{
    /**
     * @param Locator $locator The locator that records the names
     */
    public function __construct(public readonly Locator $locator)
    {
    }

    /**
     * Locates the names of a statement that writes rows, and tells whether it is one.
     */
    public function locate(Node $statement): bool
    {
        if ($statement instanceof Update) {
            $this->update($statement);

            return true;
        }
        if ($statement instanceof InsertRows || $statement instanceof InsertSet) {
            $this->values($statement);

            return true;
        }
        if ($statement instanceof InsertQuery) {
            $this->query($statement);

            return true;
        }
        if ($statement instanceof Delete) {
            $this->delete($statement);

            return true;
        }

        return false;
    }

    /**
     * Locates the names of an UPDATE: WHERE, the assignments, then ORDER BY.
     */
    public function update(Update $statement): void
    {
        $this->locator->visit($statement->where, 'where clause', [1]);
        $this->locator->assignments($statement->assignments, [2]);
        $this->locator->visit($statement->orderBy, 'order clause', [6]);
    }

    /**
     * Locates the names of an INSERT of rows or of SET: in MySQL 5.6 the first row, or the values of SET, before the columns.
     */
    public function values(InsertRows|InsertSet $statement): void
    {
        $locator = $this->locator;
        if ($locator->release !== GrammarRelease::MySql5651) {
            $locator->visit($statement->into, 'field list', [0]);
            if ($statement instanceof InsertRows) {
                $locator->visit($statement->rows, 'field list', [1]);
            } else {
                $locator->assignments($statement->assignments, [1]);
            }
            $locator->visit($statement->alias, 'field list', [2]);
            $locator->assignments($statement->onDuplicate, [3]);

            return;
        }
        if ($statement instanceof InsertRows) {
            $locator->visit($statement->rows[0] ?? null, 'field list', [0]);
            $locator->visit($statement->into, 'field list', [1]);
            $locator->visit(array_slice($statement->rows, 1), 'field list', [2]);
        } else {
            $locator->visit(array_map(static fn (Assignment $assignment): Scalar => $assignment->value, $statement->assignments), 'field list', [1, 0]);
            $locator->visit(array_map(static fn (Assignment $assignment): ColumnUse => $assignment->column, $statement->assignments), 'field list', [1, 1]);
        }
        $locator->visit($statement->alias, 'field list', [3]);
        $locator->assignments($statement->onDuplicate, [4]);
    }

    /**
     * Locates the names of an INSERT ... SELECT: the columns of ON DUPLICATE KEY UPDATE before the query; in MySQL 5.6 and 5.7 the query before the column list.
     */
    public function query(InsertQuery $statement): void
    {
        $locator = $this->locator;
        if ($locator->release === GrammarRelease::MySql5651 || $locator->release === GrammarRelease::MySql5744) {
            $locator->visit($statement->source, 'field list', [0]);
            $locator->visit($statement->into, 'field list', [1]);
            $locator->assignments($statement->onDuplicate, [2]);

            return;
        }
        $locator->visit($statement->into, 'field list', [0]);
        $locator->visit(array_map(static fn (Assignment $assignment): ColumnUse => $assignment->column, $statement->onDuplicate), 'field list', [1]);
        $locator->visit($statement->source, 'field list', [2]);
        $locator->visit(array_map(static fn (Assignment $assignment): Scalar => $assignment->value, $statement->onDuplicate), 'field list', [3]);
    }

    /**
     * Locates the names of a DELETE: WHERE, then ORDER BY.
     */
    public function delete(Delete $statement): void
    {
        $this->locator->visit($statement->where, 'where clause', [2]);
        $this->locator->visit($statement->orderBy, 'order clause', [6]);
    }
}
