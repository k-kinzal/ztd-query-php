<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Table;

use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Snapshot;

/**
 * A relation name an open context has no declaration for; it may exist in the database.
 *
 * @visibility public
 * @example Keeping the missing declaration of a named input
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t');
 *     $query->facts->relation($query->inputRelation())->table->missing->name->name->value // => 't'
 */
final class UndeclaredTable implements TableResolution
{
    use Snapshot;

    /**
     * @param UndeclaredRelation $missing The missing declaration
     */
    public function __construct(public readonly UndeclaredRelation $missing)
    {
    }
}
