<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\TableResolution;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The resolution of a table name that a complete context does not declare and IF EXISTS allows to be absent.
 *
 * DROP TABLE IF EXISTS of such a table succeeds; the server only adds a
 * note. It is not a diagnostic.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-table.html.
 *
 * @visibility SqlSemantics
 */
final class AbsentTable implements TableResolution
{
    use Snapshot;

    /**
     * @param QualifiedName $name The name as the statement wrote it
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }
}
