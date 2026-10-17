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
     * Retains contradictory fixed columns only as a deferred stored instruction.
     */
    public function of(Query $query): QueryRoot|DeferredQuery
    {
        return (new ReadColumns())->problem($query) === null ? new QueryRoot($query) : new DeferredQuery($query);
    }
}
