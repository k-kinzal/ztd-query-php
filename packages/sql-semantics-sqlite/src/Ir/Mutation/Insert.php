<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete INSERT with a checked row source or target default input.
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
     * @param ValuesQuery|Select|DefaultRow $input The checked source of rows
     * @param InsertConflict $conflict The statement's conflict policy
     */
    public function __construct(
        public readonly TableSource $target,
        public readonly ValuesQuery|Select|DefaultRow $input,
        public readonly InsertConflict $conflict = InsertConflict::Inherit,
    ) {
        $width = count($target->columns);
        if ($input instanceof DefaultRow) {
            Check::input($width === 0, 'A DEFAULT VALUES input has no supplied target columns.');
        } else {
            Check::input($width !== 0, 'An INSERT must name at least one target column.');
            if ($input instanceof Select) {
                Check::input(!$input->source instanceof LocalSource, 'The INSERT projection requires an independent source without a local definition.');
            }
            $inputWidth = $input instanceof ValuesQuery ? count($input->rows[0]->values) : count($input->columns);
            Check::input($width === $inputWidth, 'INSERT target width must match the input rows.');
        }
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return $this->input instanceof DefaultRow
            ? 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters'
            : $this->input->profileId();
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

        $head = 'INSERT' . $conflict . ' INTO ' . $this->target->schema->quoted() . '.' . $this->target->table->quoted();
        if ($this->input instanceof DefaultRow) {
            return $head . ' DEFAULT VALUES';
        }

        return $head . ' (' . implode(', ', $columns) . ') ' . $this->input->toString();
    }
}
