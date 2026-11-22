<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A relation name that several distinct declarations of the context claim; none is chosen.
 *
 * @visibility SqlSemantics
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
