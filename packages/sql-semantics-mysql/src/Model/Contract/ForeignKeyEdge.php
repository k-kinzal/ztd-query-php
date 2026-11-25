<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Contract;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A native persistent child and its referenced parent in the catalog snapshot.
 * @visibility SqlSemantics
 */
final class ForeignKeyEdge
{
    use Snapshot;

    /**
     * Holds the declaration coordinates after native schema qualification.
     */
    public function __construct(public readonly QualifiedName $child, public readonly QualifiedName $parent)
    {
        Check::input(
            $child->schema !== null && $parent->schema !== null && $child->catalog === null && $parent->catalog === null,
            'A MySQL foreign key uses database-qualified child and parent declarations.'
        );
    }
}
