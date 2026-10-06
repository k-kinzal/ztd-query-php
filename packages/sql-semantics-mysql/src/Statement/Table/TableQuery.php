<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * The query of CREATE TABLE ... SELECT, with the handling of rows that duplicate a unique key.
 *
 * The rows of the query fill the new table. IGNORE discards a row that
 * duplicates a unique key value, REPLACE replaces the old row; without
 * either such a row is an error. The word AS before the query is optional
 * and changes nothing. Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-select.html.
 *
 * @visibility public
 * @example Reading the query part
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t IGNORE AS SELECT 1 AS a');
 *     $create->statement->query->duplicate // => \SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling::Ignore
 */
final class TableQuery implements Node
{
    use Snapshot;

    /**
     * @param Query $query The query whose rows fill the table
     * @param DuplicateHandling|null $duplicate IGNORE or REPLACE, when written
     */
    public function __construct(public readonly Query $query, public readonly ?DuplicateHandling $duplicate = null)
    {
    }

    /**
     * Writes the duplicate handling and the query.
     */
    public function render(Output $out): void
    {
        if ($this->duplicate !== null) {
            $out->keyword($this->duplicate->value);
        }
        $out->keyword('AS')->node($this->query);
    }
}
