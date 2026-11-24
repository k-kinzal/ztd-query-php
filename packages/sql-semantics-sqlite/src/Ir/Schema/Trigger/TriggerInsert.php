<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One ordered native insertion in a checked trigger program.
 *
 * @visibility public
 * @example Logging a single literal into a resolved audit table
 *     $name = static fn (string $name) => new \SqlSemantics\Platform\Sqlite\Ir\Identifier($name);
 *     $table = new \SqlSemantics\Platform\Sqlite\Ir\TableSource($name('main'), $name('audit'), [$name('event')]);
 *     $target = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination($table, [0]);
 *     $value = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('7');
 *     $field = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertField(0, $value);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsert($target, [$field]))->conflict->value // => 'inherit'
 */
final class TriggerInsert
{
    use Snapshot;

    /**
     * @var non-empty-list<TriggerInsertField> The supplied columns in evaluation order
     */
    public readonly array $fields;

    /**
     * Checks each written slot in the selected destination.
     * @param list<TriggerInsertField> $fields The ordered destination and value associations
     * @throws InvalidConstruction When a written slot is repeated or not writable
     */
    public function __construct(
        public readonly TriggerDestination $destination,
        array $fields,
        public readonly InsertConflict $conflict = InsertConflict::Inherit,
    ) {
        $list = Check::listOf($fields, TriggerInsertField::class, 'A trigger insertion needs an ordered nonempty list of supplied fields.', 1);
        $seen = [];
        foreach ($list as $field) {
            Check::input(
                in_array($field->column, $destination->writable, true) && !isset($seen[$field->column]),
                'A trigger insertion must write distinct available slots of its destination.'
            );
            $seen[$field->column] = true;
        }
        $this->fields = $list;
    }
}
