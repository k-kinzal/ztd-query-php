<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One ordered VALUES tuple in a resolved SQLite trigger insertion.
 *
 * @visibility public
 * @example Recording the positions supplied by the tuple
 *     $field = new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertField(1, new \SqlSemantics\Platform\Sqlite\Ir\NullLiteral());
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertRow([$field]))->columns() // => [1]
 */
final class TriggerInsertRow
{
    use Snapshot;

    /**
     * @var non-empty-list<TriggerInsertField> The supplied columns and values in native order
     */
    public readonly array $fields;

    /**
     * Copies an ordered tuple and refuses repeated destination positions.
     *
     * @param list<TriggerInsertField> $fields The ordered cell associations
     */
    public function __construct(array $fields)
    {
        $list = Check::listOf($fields, TriggerInsertField::class, 'A trigger insertion tuple needs an ordered nonempty list of supplied fields.', 1);
        $seen = [];
        foreach ($list as $field) {
            Check::input(!isset($seen[$field->column]), 'A trigger insertion must write distinct available slots of its destination.');
            $seen[$field->column] = true;
        }
        $this->fields = $list;
    }

    /**
     * Returns the tuple's exact writable positions in order.
     *
     * @return non-empty-list<int>
     */
    public function columns(): array
    {
        $positions = [];
        foreach ($this->fields as $field) {
            $positions[] = $field->column;
        }

        return $positions;
    }
}
