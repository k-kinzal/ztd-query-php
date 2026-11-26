<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A query-local row source and the named slots it supplies.
 *
 * @visibility public
 * @example Reading the name of a bound local slot
 *     $query = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('WITH c(id) AS (VALUES (1)) SELECT id FROM c');
 *     $query->source->columns[0]->value // => 'id'
 */
final class LocalSource
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier>
     */
    public readonly array $columns;

    /**
     * Checks the name and width of every defined column.
     *
     * @param list<Identifier> $columns
     */
    public function __construct(public readonly ValuesQuery|RecursiveDefinition $query, array $columns)
    {
        $names = Check::listOf($columns, Identifier::class, 'A local source needs an ordered list of column names.', 1);
        $anchor = $query instanceof RecursiveDefinition ? $query->anchor : $query;
        Check::input(count($names) === count($anchor->rows[0]->values), 'Local source names must match the VALUES width.');
        $seen = [];
        foreach ($names as $name) {
            $key = $name->key();
            Check::input(!isset($seen[$key]), 'A local source cannot repeat a column name.');
            $seen[$key] = true;
        }
        $this->columns = $names;
    }
}
