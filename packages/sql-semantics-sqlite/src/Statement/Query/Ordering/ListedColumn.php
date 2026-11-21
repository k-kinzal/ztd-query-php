<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Ordering;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One name of a column name list, with the collation and direction the grammar admits after it.
 *
 * Column lists of common table expressions, views and foreign keys use this
 * form. SQLite parses a collation and a direction there for compatibility
 * with old index syntax and rejects them when the statement is prepared.
 * Source: https://sqlite.org/syntax/indexed-column.html.
 *
 * @visibility public
 * @example Reading the column names of a common table expression
 *     $query = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('WITH c(x, y) AS (SELECT 1, 2) SELECT x FROM c');
 *     $query->statement->with->tables[0]->columns[1]->name->value // => 'y'
 */
final class ListedColumn implements Node
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param Name|null $collation The collation written after the name
     * @param SortDirection|null $direction The direction written after the name
     */
    public function __construct(public readonly Name $name, public readonly ?Name $collation = null, public readonly ?SortDirection $direction = null)
    {
    }

    /**
     * Writes the name, the collation and the direction.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->name($this->collation, NameUse::Label);
        }
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
    }
}
