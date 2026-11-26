<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\TableResolution;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A relation name that a complete context does not declare, at a use that tolerates the absence.
 *
 * `DROP TABLE IF EXISTS` and `DROP VIEW IF EXISTS` do nothing when the
 * relation does not exist, so the absence is a resolution and not a problem.
 * Source: https://sqlite.org/lang_droptable.html.
 *
 * @visibility SqlSemantics
 */
final class AbsentRelation implements TableResolution
{
    use Snapshot;

    /**
     * @param QualifiedName $name The name as the statement wrote it
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }
}
