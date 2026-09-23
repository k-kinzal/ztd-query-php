<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Foreign;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql as Statement;

/**
 * Writes mapping identities without resolving symbolic principals to runtime usernames.
 * @visibility SqlSemantics
 */
final class UserMappings
{
    /**
     * Serializes distinct creation, modification, and removal operands.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof Statement\CreateUserMappingStatement && !$statement instanceof Statement\AlterUserMappingStatement && !$statement instanceof Statement\DropUserMappingStatement) {
            return null;
        }
        $operation = match (true) {
            $statement instanceof Statement\CreateUserMappingStatement => 'CREATE USER MAPPING' . ($statement->ifNotExists ? ' IF NOT EXISTS' : ''),
            $statement instanceof Statement\AlterUserMappingStatement => 'ALTER USER MAPPING',
            $statement instanceof Statement\DropUserMappingStatement => 'DROP USER MAPPING' . ($statement->ifExists ? ' IF EXISTS' : ''),
        };
        $options = match (true) {
            $statement instanceof Statement\CreateUserMappingStatement => array_map(static fn (ForeignOption $option): Tree => WrapperOptions::option($option), $statement->options),
            $statement instanceof Statement\AlterUserMappingStatement => array_map(static fn (AddForeignOption|SetForeignOption|DropForeignOption $option): Tree => WrapperOptions::change($option), $statement->options),
            $statement instanceof Statement\DropUserMappingStatement => [],
        };
        $target = $statement->target;
        return new Tree('user-mapping', [
            Build::keyword($operation . ' FOR'), $target->user instanceof NamedRole ? Build::identifier([$target->user->name], Dialect::PostgreSql) : Build::keyword($target->user->value),
            Build::keyword('SERVER'), Build::identifier([$target->server], Dialect::PostgreSql),
            ...($options === [] ? [] : [Build::keyword('OPTIONS'), Build::parentheses(Build::separated($options))]),
        ]);
    }
}
