<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Table;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A relation name that a complete context does not declare.
 *
 * @visibility public
 * @example Establishing that a table does not exist
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t', []);
 *     $query->facts->relation($query->inputRelation())->table->message() // => 'Relation t does not exist.'
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
