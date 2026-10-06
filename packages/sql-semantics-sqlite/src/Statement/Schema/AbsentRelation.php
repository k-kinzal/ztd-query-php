<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Snapshot;

/**
 * A relation name that a complete context does not declare, at a use that tolerates the absence.
 *
 * `DROP TABLE IF EXISTS` and `DROP VIEW IF EXISTS` do nothing when the
 * relation does not exist, so the absence is a resolution and not a problem.
 * Source: https://sqlite.org/lang_droptable.html.
 *
 * @visibility public
 * @example Dropping a table that does not exist without a diagnostic
 *     $drop = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('DROP TABLE IF EXISTS t', []);
 *     [$drop->facts->relation($drop->statement)->table->name->name->value, $drop->facts->diagnostics] // => ['t', []]
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
