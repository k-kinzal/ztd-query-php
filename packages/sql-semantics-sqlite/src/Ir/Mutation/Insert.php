<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete INSERT of ordered rows into declared writable column slots.
 *
 * @visibility public
 * @example Preparing a checked INSERT operation
 *     $sqlite = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $catalog = $sqlite->context(['CREATE TABLE items (id INTEGER)']);
 *     $sqlite->analyze('INSERT INTO items (id) VALUES (1)', $catalog) instanceof \SqlSemantics\Platform\Sqlite\Ir\Mutation\Insert // => true
 */
final class Insert implements SemanticsIR
{
    use Snapshot;

    /**
     * Pairs each value position with a writable target column.
     *
     * @param TableSource $target The absolute target and its ordered writable column slots
     * @param ValuesQuery $input The rows supplying the column values
     * @param InsertConflict $conflict The statement's conflict policy
     */
    public function __construct(
        public readonly TableSource $target,
        public readonly ValuesQuery $input,
        public readonly InsertConflict $conflict = InsertConflict::Inherit,
    ) {
        $width = count($target->columns);
        Check::input($width !== 0, 'An INSERT must name at least one target column.');
        Check::input($width === count($input->rows[0]->values), 'INSERT target width must match the input rows.');
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return $this->input->profileId();
    }

    /**
     * Writes the target and the values using the same slot order.
     */
    public function toString(): string
    {
        $columns = [];
        foreach ($this->target->columns as $column) {
            $columns[] = $column->quoted();
        }
        $conflict = match ($this->conflict) {
            InsertConflict::Inherit => '',
            InsertConflict::Abort => ' OR ABORT',
            InsertConflict::Ignore => ' OR IGNORE',
        };

        return 'INSERT' . $conflict . ' INTO ' . $this->target->schema->quoted() . '.' . $this->target->table->quoted()
            . ' (' . implode(', ', $columns) . ') ' . $this->input->toString();
    }
}
