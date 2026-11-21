<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Table;

use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A relation name that denotes a common table expression of the same statement.
 *
 * @visibility public
 * @example Resolving a name to the common table that defines it
 *     $query = (new \SqlSemantics\Platform\MySql\Semantics())->analyze('WITH c AS (SELECT 1 AS a) SELECT a FROM c');
 *     $query->facts->relation($query->statement->body->input())->table instanceof \SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\CommonTable // => true
 */
final class CommonTable implements TableResolution
{
    use Snapshot;

    /**
     * @param Node $definition The common table definition of the statement
     */
    public function __construct(public readonly Node $definition)
    {
    }
}
