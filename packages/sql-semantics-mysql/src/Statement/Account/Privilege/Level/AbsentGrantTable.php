<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level;

use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Snapshot;

/**
 * The resolution of a table name a complete context does not declare, at a grant level where the table need not exist.
 *
 * GRANT may name a table that does not exist when the granted privileges
 * include CREATE and no column privilege is granted; REVOKE does not check
 * the table at all. It is not a diagnostic.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-table-privileges
 * ("the privileges to be granted must include the CREATE privilege").
 *
 * @visibility public
 * @example Granting CREATE on a table that does not exist yet
 *     $grant = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('GRANT CREATE ON t TO u', []);
 *     $grant->facts->relation($grant->statement->level)->table instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\AbsentGrantTable // => true
 */
final class AbsentGrantTable implements TableResolution
{
    use Snapshot;

    /**
     * @param QualifiedName $name The name as the statement wrote it
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }
}
