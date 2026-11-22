<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Table;

use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A relation name that denotes exactly one declaration of the context.
 *
 * @visibility SqlSemantics
 */
final class DeclaredTable implements TableResolution
{
    use Snapshot;

    /**
     * @param Table $table The declaration object of the context
     */
    public function __construct(public readonly Table $table)
    {
    }
}
