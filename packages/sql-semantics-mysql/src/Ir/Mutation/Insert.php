<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete literal INSERT into one declared base table.
 *
 * @visibility public
 * @example Publishing a checked literal insert
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $value = $semantics->analyze('INSERT INTO app.t (n) VALUES (NULL)', $semantics->context(['CREATE TABLE app.t (n INTEGER)']));
 *     $value->toString() // => 'INSERT INTO `app`.`t` (`n`) VALUES (NULL)'
 */
final class Insert implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<InsertRow> The ordered rows for this write
     */
    public readonly array $rows;

    /**
     * Establishes the target and shape conditions before publishing this write.
     *
     * @param list<InsertRow> $rows
     */
    public function __construct(
        public readonly TableSource $target,
        array $rows,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
    ) {
        Check::input($target->kind->isTable(), 'An INSERT target must be a base table.');
        $width = count($target->columns);
        Check::input($width >= 1 && $width <= 1024, 'An INSERT needs between one and 1024 target columns.');
        foreach ($target->columnTypes as $type) {
            Check::input($type instanceof ColumnKind || $type instanceof Varchar, 'An INSERT destination requires a writable signed INT or native VARCHAR slot.');
        }
        $this->rows = Check::listOf($rows, InsertRow::class, 'An INSERT needs a nonempty list of rows.', 1);
        Check::input(count($this->rows) <= 512 && count($this->rows) * $width <= 8192, 'The supplied INSERT values exceed the profile limits.');
        foreach ($this->rows as $row) {
            Check::input(count($row->values) === $width, 'Every inserted row must match the target width.');
            foreach ($row->values as $position => $cell) {
                Check::input(
                    $cell->value === null || ($cell->value instanceof TextLiteral) === ($target->columnTypes[$position] instanceof Varchar),
                    'An INSERT value must match its native destination type.',
                );
            }
        }
    }

    /**
     * Identifies the pinned native language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native';
    }

    /**
     * Writes the absolute target and the explicit column and value order.
     */
    public function toString(): string
    {
        $target = $this->target;
        $columns = [];
        foreach ($target->columns as $column) {
            $columns[] = $column->quoted();
        }
        $rows = [];
        foreach ($this->rows as $row) {
            $rows[] = $row->toString();
        }

        return 'INSERT INTO ' . $target->schema->quoted() . '.' . $target->table->quoted()
            . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $rows);
    }
}
