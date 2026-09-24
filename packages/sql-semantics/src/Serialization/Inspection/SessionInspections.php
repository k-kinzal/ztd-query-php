<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Inspection;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Query\Inspection\RowWindow;
use SqlSemantics\Model\Query\Inspection\Session\VariableScope;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Inspection\Session;
use SqlSemantics\Serialization\Definition\MySqlRemovals;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes variable, status, diagnostics, grant, and replication inspections from their operands.
 * @visibility SqlSemantics
 */
final class SessionInspections
{
    /**
     * Returns null for statements outside this inspection family; the session scope and the authenticated account are written implicitly.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Session\ShowVariablesStatement => new Tree('show', [Build::keyword('SHOW' . self::scope($statement->scope) . ' VARIABLES'), ...Filters::write($statement->filter)]),
            $statement instanceof Session\ShowStatusStatement => new Tree('show', [Build::keyword('SHOW' . self::scope($statement->scope) . ' STATUS'), ...Filters::write($statement->filter)]),
            $statement instanceof Session\ShowDiagnosticsStatement => new Tree('show', [Build::keyword('SHOW ' . $statement->selection->value), ...self::window($statement->limit)]),
            $statement instanceof Session\ShowDiagnosticCountStatement => Build::keyword('SHOW COUNT(*) ' . $statement->selection->value),
            $statement instanceof Session\ShowGrantsStatement => self::grants($statement),
            default => ReplicationInspections::write($statement),
        };
    }

    /**
     * Writes the GLOBAL keyword; session values need no keyword.
     */
    public static function scope(VariableScope $scope): string
    {
        return match ($scope) {
            VariableScope::Global => ' GLOBAL',
            VariableScope::Session => '',
        };
    }

    /**
     * @return list<Tree> LIMIT with the count and optional OFFSET, or nothing
     */
    public static function window(?RowWindow $limit): array
    {
        if ($limit === null) {
            return [];
        }
        return [Build::keyword('LIMIT'), Expressions::write($limit->count), ...($limit->offset === null ? [] : [Build::keyword('OFFSET'), Expressions::write($limit->offset)])];
    }

    /**
     * The authenticated account without roles is written without FOR; roles require the account to be named.
     */
    public static function grants(Session\ShowGrantsStatement $statement): Tree
    {
        if ($statement->account === CurrentAccount::Authenticated && $statement->roles === []) {
            return Build::keyword('SHOW GRANTS');
        }
        $roles = $statement->roles;
        return new Tree('show', [
            Build::keyword('SHOW GRANTS FOR'),
            MySqlRemovals::accounts([$statement->account]),
            ...($roles === [] ? [] : [Build::keyword('USING'), MySqlRemovals::accounts($roles)]),
        ]);
    }
}
