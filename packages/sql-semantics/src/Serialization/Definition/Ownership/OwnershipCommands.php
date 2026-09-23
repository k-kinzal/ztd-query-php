<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Ownership;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\DropOwnedStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\ReassignOwnedStatement;

/**
 * Serializes owner selections with separate identifier and session-role syntax.
 * @visibility SqlSemantics
 */
final class OwnershipCommands
{
    /**
     * Writes the complete typed ownership operation.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof DropOwnedStatement && !$statement instanceof ReassignOwnedStatement) {
            return null;
        }
        $tail = $statement instanceof ReassignOwnedStatement
            ? [Build::keyword('TO'), self::role($statement->newOwner)]
            : ($statement->behavior->value === '' ? [] : [Build::keyword($statement->behavior->value)]);
        return new Tree('owned-objects', [
            Build::keyword($statement instanceof DropOwnedStatement ? 'DROP OWNED BY' : 'REASSIGN OWNED BY'),
            Build::separated(array_map(self::role(...), $statement->owners)),
            ...$tail,
        ]);
    }

    /**
     * Quotes named roles and preserves session lookup rules as keywords.
     */
    public static function role(NamedRole|SessionRole $role): Tree
    {
        return $role instanceof NamedRole ? Build::identifier([$role->name], Dialect::PostgreSql) : Build::keyword($role->value);
    }
}
