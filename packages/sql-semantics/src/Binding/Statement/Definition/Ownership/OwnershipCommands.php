<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Ownership;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\PostgreSqlRoles;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\DropOwnedStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\ReassignOwnedStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Structures ownership operations without enumerating or changing owned objects.
 * @visibility SqlSemantics
 */
final class OwnershipCommands
{
    /**
     * Requires source owners and, for transfer, a separate destination role.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source): DropOwnedStatement|ReassignOwnedStatement|null
    {
        if ($origin->dialect !== Dialect::PostgreSql || !in_array($source->name, ['DropOwnedStmt', 'ReassignOwnedStmt'], true)) {
            return null;
        }
        $list = Tree::child($source, ['role_list']) ?? throw new UnclassifiedSql('Ownership operations require their source role list.');
        $owners = Collections::nonEmpty(array_map(PostgreSqlRoles::read(...), Tree::outer($list, ['RoleSpec'])));
        if ($source->name === 'ReassignOwnedStmt') {
            $destination = Tree::child($source, ['RoleSpec']) ?? throw new UnclassifiedSql('Ownership transfer requires its destination role.');
            return new ReassignOwnedStatement($origin, $owners, PostgreSqlRoles::read($destination));
        }
        $behavior = Tree::child($source, ['opt_drop_behavior']);
        return new DropOwnedStatement($origin, $owners, $behavior === null ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behavior))));
    }
}
