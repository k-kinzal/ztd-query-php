<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Snapshot;

/**
 * The resolution of a table name that a complete context does not declare and IF EXISTS allows to be absent.
 *
 * DROP TABLE IF EXISTS of such a table succeeds; the server only adds a
 * note. It is not a diagnostic.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-table.html.
 *
 * @visibility public
 * @example Resolving an absent table that IF EXISTS allows
 *     $drop = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DROP TABLE IF EXISTS t', []);
 *     $drop->facts->relation($drop->statement->tables[0])->table instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable // => true
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
