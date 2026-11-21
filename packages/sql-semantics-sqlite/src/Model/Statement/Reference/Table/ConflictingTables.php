<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A relation name that several distinct declarations of the context claim; none is chosen.
 *
 * @visibility public
 * @example Keeping conflicting declarations
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $declarations = $semantics->context([$semantics->inspect('CREATE TABLE t (a INTEGER)'), $semantics->inspect('CREATE TABLE t (b TEXT)')]);
 *     $semantics->inspect('SELECT 1 FROM t', $declarations) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\SemanticException: Relation t has conflicting declarations.
 */
final class ConflictingTables implements TableResolution, Diagnostic
{
    use Snapshot;

    /**
     * @var list<Table> The declarations that claim the name
     */
    public readonly array $candidates;

    /**
     * @param QualifiedName $name The name as the statement wrote it
     * @param list<Table> $candidates The declarations that claim the name; at least two
     */
    public function __construct(public readonly QualifiedName $name, array $candidates)
    {
        $this->candidates = Check::listOf($candidates, Table::class, 'A declaration conflict has at least two declarations.', 2);
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Relation ' . $this->name->name->value . ' has conflicting declarations.';
    }
}
