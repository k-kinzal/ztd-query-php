<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

/**
 * The relation actions on one named object: an index, a constraint, a tablespace, a trigger or a rule.
 *
 * Mirrors `AT_ClusterOn`, `AT_ValidateConstraint`, `AT_SetTableSpace` and the trigger and rule firing actions.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading the keywords of a named action
 *     \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\NamedActionKind::ClusterOn->keywords() // => ['CLUSTER', 'ON']
 */
enum NamedActionKind: string
{
    case ClusterOn = 'CLUSTER ON';
    case ValidateConstraint = 'VALIDATE CONSTRAINT';
    case SetTablespace = 'SET TABLESPACE';
    case EnableTrigger = 'ENABLE TRIGGER';
    case EnableAlwaysTrigger = 'ENABLE ALWAYS TRIGGER';
    case EnableReplicaTrigger = 'ENABLE REPLICA TRIGGER';
    case DisableTrigger = 'DISABLE TRIGGER';
    case EnableRule = 'ENABLE RULE';
    case EnableAlwaysRule = 'ENABLE ALWAYS RULE';
    case EnableReplicaRule = 'ENABLE REPLICA RULE';
    case DisableRule = 'DISABLE RULE';

    /**
     * Answers the keywords.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return explode(' ', $this->value);
    }
}
