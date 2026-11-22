<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A relation name that a complete context does not declare.
 *
 * @visibility SqlSemantics
 */
final class MissingTable implements TableResolution, Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $name The name as the statement wrote it
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Relation ' . $this->name->name->value . ' does not exist.';
    }
}
