<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Trigger;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy as Statement;
use SqlSemantics\Serialization\Definition\Role\RoleSpecs;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes policy definitions with explicit mode, command, and roles, and alterations with only their changes.
 * @visibility SqlSemantics
 */
final class Policies
{
    /**
     * Returns null for other statements.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if ($statement instanceof Statement\CreatePolicyStatement) {
            return new Tree('create-policy', [
                Build::keyword('CREATE POLICY'),
                Build::identifier([$statement->name], Dialect::PostgreSql),
                Build::keyword('ON'),
                Relations::target($statement->table, Dialect::PostgreSql),
                Build::keyword('AS ' . $statement->mode->value . ' FOR ' . $statement->command->value . ' TO'),
                RoleSpecs::roles($statement->roles),
                ...self::expressions($statement->using, $statement->check),
            ]);
        }
        if (!$statement instanceof Statement\AlterPolicyStatement) {
            return null;
        }
        return new Tree('alter-policy', [
            Build::keyword('ALTER POLICY'),
            Build::identifier([$statement->name], Dialect::PostgreSql),
            Build::keyword('ON'),
            Relations::target($statement->table, Dialect::PostgreSql),
            ...($statement->roles === null ? [] : [Build::keyword('TO'), RoleSpecs::roles($statement->roles)]),
            ...self::expressions($statement->using, $statement->check),
        ]);
    }

    /**
     * The USING and WITH CHECK clauses that are present.
     * @return list<Tree>
     */
    public static function expressions(?Expression $using, ?Expression $check): array
    {
        return [
            ...($using === null ? [] : [Build::keyword('USING'), Build::parentheses(Expressions::write($using))]),
            ...($check === null ? [] : [Build::keyword('WITH CHECK'), Build::parentheses(Expressions::write($check))]),
        ];
    }
}
