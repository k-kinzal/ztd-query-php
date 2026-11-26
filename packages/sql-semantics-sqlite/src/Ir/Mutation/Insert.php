<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalInputs;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
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
     * @var list<ColumnReference> Target slots in input position order
     */
    public readonly array $writes;

    /**
     * @var list<ReturnedColumn> The ordered fields of the directly inserted rows
     */
    public readonly array $returning;

    /**
     * Pairs each value position with a writable target column.
     *
     * @param TableSource $target The absolute target with its consumed field slots
     * @param list<ColumnReference> $writes The writable slots in the order supplied by the input
     * @param ValuesQuery|Select|DefaultRow $input The checked source of rows
     * @param InsertConflict $conflict The statement's conflict policy
     * @param list<ReturnedColumn> $returning The ordered fields read from each inserted row
     * @param InsertConflictHandler $handler The optional action on a uniqueness conflict
     */
    public function __construct(
        public readonly TableSource $target,
        array $writes,
        public readonly ValuesQuery|Select|DefaultRow $input,
        public readonly InsertConflict $conflict = InsertConflict::Inherit,
        array $returning = [],
        public readonly InsertConflictHandler $handler = InsertConflictHandler::None,
    ) {
        Check::input($target->kind === ExternalRelationKind::BaseTable, 'A mutation target must be a base table.');
        $supplied = Check::listOf($writes, ColumnReference::class, 'An INSERT needs checked write slots.');
        $seen = [];
        foreach ($supplied as $column) {
            Check::input($column->source === 0 && isset($target->columns[$column->slot]), 'A supplied slot must belong to the INSERT target.');
            Check::input(!isset($seen[$column->slot]), 'An INSERT cannot repeat a supplied column.');
            $seen[$column->slot] = true;
        }
        $width = count($supplied);
        if ($input instanceof DefaultRow) {
            Check::input($handler === InsertConflictHandler::None, 'DEFAULT VALUES cannot take an INSERT conflict handler.');
            Check::input($width === 0, 'A DEFAULT VALUES input has no supplied target columns.');
        } else {
            Check::input($width !== 0, 'An INSERT must name at least one target column.');
            if ($input instanceof Select) {
                Check::input((new LocalInputs())->occurrences($input->source, $input->joins) === [], 'The INSERT projection requires an independent source without a local definition.');
            }
            $inputWidth = $input instanceof ValuesQuery ? count($input->rows[0]->values) : count($input->columns);
            Check::input($width === $inputWidth, 'INSERT target width must match the input rows.');
        }
        $output = Check::listOf($returning, ReturnedColumn::class, 'An INSERT needs checked RETURNING fields.');
        Check::input(count($output) <= 2000, 'An INSERT can return at most 2000 fields in this profile.');
        foreach ($output as $column) {
            Check::input(isset($target->columns[$column->reference->slot]), 'A returned slot must belong to the INSERT target.');
        }
        $this->writes = $supplied;
        $this->returning = $output;
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
        foreach ($this->writes as $column) {
            $columns[] = $this->target->columns[$column->slot]->quoted();
        }
        $conflict = match ($this->conflict) {
            InsertConflict::Inherit => '',
            InsertConflict::Abort => ' OR ABORT',
            InsertConflict::Ignore => ' OR IGNORE',
            InsertConflict::Fail => ' OR FAIL',
            InsertConflict::Rollback => ' OR ROLLBACK',
            InsertConflict::Replace => ' OR REPLACE',
        };

        $head = 'INSERT' . $conflict . ' INTO ' . $this->target->schema->quoted() . '.' . $this->target->table->quoted();
        $returning = (new ReturningSql())->clause($this->target, $this->returning);
        if ($this->input instanceof DefaultRow) {
            return $head . ' DEFAULT VALUES' . $returning;
        }

        $source = $this->input instanceof Select && $this->handler === InsertConflictHandler::DoNothing
            ? (new SelectSql())->render($this->input, requireWhere: true)
            : $this->input->toString();
        $action = match ($this->handler) {
            InsertConflictHandler::None => '',
            InsertConflictHandler::DoNothing => ' ON CONFLICT DO NOTHING',
        };

        return $head . ' (' . implode(', ', $columns) . ') ' . $source . $action . $returning;
    }
}
