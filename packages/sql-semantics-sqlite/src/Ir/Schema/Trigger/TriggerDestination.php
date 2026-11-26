<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A resolved ordinary write target and its available physical write positions.
 *
 * @visibility public
 * @example Selecting the first writable position of an audit table
 *     $name = static fn (string $name) => new \SqlSemantics\Platform\Sqlite\Ir\Identifier($name);
 *     $table = new \SqlSemantics\Platform\Sqlite\Ir\TableSource($name('main'), $name('audit'), [$name('event')]);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination($table, [0]))->writable // => [0]
 */
final class TriggerDestination
{
    use Snapshot;

    /**
     * @var non-empty-list<int> The actual writable positions in declaration order
     */
    public readonly array $writable;

    /**
     * Checks the table kind and every candidate write position.
     * @param array<array-key, mixed> $writable The writable column positions
     * @throws InvalidConstruction When the table or its writable positions cannot be used by an insertion
     */
    public function __construct(public readonly TableSource $table, array $writable)
    {
        Check::input(
            $table->kind === ExternalRelationKind::BaseTable && !str_starts_with($table->table->key(), 'sqlite_'),
            'A trigger insertion needs an ordinary writable table.'
        );
        Check::input($writable !== [] && array_is_list($writable) && count($writable) <= 2000, 'A trigger destination needs an ordered nonempty list of writable columns.');
        $positions = [];
        $previous = -1;
        foreach ($writable as $column) {
            Check::input(
                is_int($column) && $column > $previous && isset($table->columns[$column]),
                'Trigger destination positions must be distinct, ordered members of the table.'
            );
            $positions[] = $column;
            $previous = $column;
        }
        $this->writable = $positions;
    }
}
