<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete table-creation template that cannot inhabit an ordinary statement.
 *
 * @example Keeping the incompatible target role outside an ordinary statement
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t UNION=() SELECT 1') // throws \SqlSemantics\Construction\RejectedInput
 *
 * @visibility public
 */
final class RefusedCreateTemplate implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredCreateTable $definition The complete retained header and query
     */
    public function __construct(public readonly DeferredCreateTable $definition)
    {
    }

    /**
     * Describes the target's incompatible MERGE/query role independently of the current database.
     */
    public function message(): string
    {
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles())->columnless($this->definition->elements, $this->definition->query)) {
            return 'The table definition has no declared column or creation query.';
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($this->definition->elements)) {
            return 'The table definition retains generation conditions resolved at invocation.';
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\PartitionKeyRole())->missing($this->definition->elements, $this->definition->partitioning, $this->definition->query)) {
            return 'The declaration supplies no key for implicit KEY partitioning.';
        }
        $read = (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles())->readProblem($this->definition->query);
        if ($read !== null) {
            return $read->message();
        }
        $declaration = (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\DeclarationConstraints())->problem($this->definition->elements, $this->definition->query);
        if ($declaration !== null) {
            return $declaration->message();
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->elements($this->definition->elements)) {
            return 'The table definition retains a non-scalar index operand.';
        }
        return 'A MERGE source list and a creation query do not define a base table.';
    }
}
