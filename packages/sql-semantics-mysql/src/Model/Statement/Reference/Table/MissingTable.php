<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Table;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A relation name that a complete context does not declare.
 *
 * @visibility public
 * @example Establishing that a table does not exist
 *     (new \SqlSemantics\Platform\MySql\Semantics())->analyze('SELECT a FROM t', []) // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\SemanticException: Relation t does not exist.
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
