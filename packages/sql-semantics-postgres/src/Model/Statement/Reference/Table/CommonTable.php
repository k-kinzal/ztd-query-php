<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A relation name that denotes a common table expression of the same statement.
 *
 * @visibility SqlSemantics
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
