<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Table;

use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A relation name an open context has no declaration for; it may exist in the database.
 *
 * @visibility SqlSemantics
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
