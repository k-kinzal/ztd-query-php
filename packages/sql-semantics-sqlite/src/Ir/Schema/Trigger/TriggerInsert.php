<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One native insertion and its ordered tuples in a checked trigger program.
 *
 * @visibility public
 * @example Logging a single literal into a resolved audit table
 *     $name = static fn (string $name) => new \SqlSemantics\Platform\Sqlite\Ir\Identifier($name);
 *     $table = new \SqlSemantics\Platform\Sqlite\Ir\TableSource($name('main'), $name('audit'), [$name('event')]);
 *     $target = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination($table, [0]);
 *     $value = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('7');
 *     $field = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertField(0, $value);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsert($target, [new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertRow([$field])]))->conflict->value // => 'inherit'
 */
final class TriggerInsert
{
    use Snapshot;

    /**
     * @var non-empty-list<TriggerInsertRow> The tuples of this native statement in submission order
     */
    public readonly array $rows;

    /**
     * Checks every row against the common writable destination heading.
     *
     * @param list<TriggerInsertRow> $rows The ordered VALUES tuples
     * @throws InvalidConstruction When the tuples cannot share one checked destination heading
     */
    public function __construct(
        public readonly TriggerDestination $destination,
        array $rows,
        public readonly InsertConflict $conflict = InsertConflict::Inherit,
    ) {
        $tuples = Check::listOf($rows, TriggerInsertRow::class, 'A trigger insertion needs a nonempty ordered row list.', 1);
        $heading = $tuples[0];
        foreach ($heading->fields as $field) {
            Check::input(in_array($field->column, $destination->writable, true), 'A trigger insertion must write distinct available slots of its destination.');
        }
        foreach ($tuples as $tuple) {
            Check::input($heading->hasSameHeading($tuple), 'Every trigger insertion tuple needs the same ordered writable heading.');
        }
        $this->rows = $tuples;
    }
}
