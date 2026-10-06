<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

/**
 * The relation actions without an operand.
 *
 * Mirrors `AT_DropOids`, `AT_DropCluster`, `AT_SetLogged`, `AT_SetUnLogged`, `AT_DropOf`, the row security actions and the trigger actions on all or all user triggers.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading the keywords of a relation action
 *     \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind::NotOf->keywords() // => ['NOT', 'OF']
 */
enum TableActionKind: string
{
    case SetWithoutOids = 'SET WITHOUT OIDS';
    case SetWithoutCluster = 'SET WITHOUT CLUSTER';
    case SetLogged = 'SET LOGGED';
    case SetUnlogged = 'SET UNLOGGED';
    case NotOf = 'NOT OF';
    case EnableRowSecurity = 'ENABLE ROW LEVEL SECURITY';
    case DisableRowSecurity = 'DISABLE ROW LEVEL SECURITY';
    case ForceRowSecurity = 'FORCE ROW LEVEL SECURITY';
    case NoForceRowSecurity = 'NO FORCE ROW LEVEL SECURITY';
    case EnableAllTriggers = 'ENABLE TRIGGER ALL';
    case EnableUserTriggers = 'ENABLE TRIGGER USER';
    case DisableAllTriggers = 'DISABLE TRIGGER ALL';
    case DisableUserTriggers = 'DISABLE TRIGGER USER';

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
