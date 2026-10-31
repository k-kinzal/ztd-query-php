<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;
use SqlSemantics\Statement\Query;

/**
 * Assigns the read-statement or stored-instruction role to a complete query.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QueryMeaning
{
    /**
     * Retains contradictory fixed columns only as a deferred stored instruction. A standalone star
     * without tables is refused once the statement is read ("No tables used"), as the server does.
     *
     * @param bool $stored Whether the query belongs to a stored program body, which keeps it
     * @throws \SqlSemantics\Construction\RejectedInput When statement-level quantifiers conflict, or a standalone star has no tables
     */
    public function of(Query $query, int $end = 0, bool $stored = false): QueryRoot|DeferredQuery|\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection
    {
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\StatementQuery())->problem($query);
        if ($problem !== null) {
            throw new \SqlSemantics\Construction\RejectedInput($problem, $end, false);
        }
        if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody) {
            return $query->selection;
        }
        $columns = (new ReadColumns())->problem($query);
        if (!$stored && $columns instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse && $columns->rule === \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::StarWithoutTables) {
            throw new \SqlSemantics\Construction\RejectedInput($columns, $end, false);
        }
        return $columns === null ? new QueryRoot($query) : new DeferredQuery($query);
    }
}
