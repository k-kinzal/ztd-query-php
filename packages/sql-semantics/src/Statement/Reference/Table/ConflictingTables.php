<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Table;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A relation name that several distinct declarations of the context claim; none is chosen.
 *
 * @visibility public
 * @example Keeping conflicting declarations
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $context = $semantics->context([$semantics->analyze('CREATE TABLE t (a INTEGER)'), $semantics->analyze('CREATE TABLE t (b TEXT)')]);
 *     $query = $semantics->analyze('SELECT 1 FROM t', $context);
 *     count($query->facts->relation($query->inputRelation())->table->candidates) // => 2
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
