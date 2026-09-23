<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration\Role;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\DefaultRolePolicy;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;
use SqlSemantics\Model\Statement\Configuration\Role as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Separates session role selection from account default-role changes and variable assignment.
 * @visibility SqlSemantics
 */
final class RoleBinder
{
    /**
     * Recognizes the complete MySQL SET ROLE grammar, including default-role recipients.
     */
    public static function bind(Origin $origin, Node $node, Identifiers $identifiers): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql || $node->name !== 'set_role_stmt') {
            return null;
        }
        $words = array_map(static fn ($token): string => strtoupper($token->text), $node->tokens());
        $lists = Tree::outer($node, ['role_list']);
        if (($words[1] ?? '') === 'DEFAULT') {
            $accounts = AccountNames::read($lists[count($lists) - 1], $identifiers);
            return count($lists) === 2
                ? new Statement\SetDefaultRolesStatement($origin, AccountNames::read($lists[0], $identifiers), $accounts)
                : new Statement\SetDefaultRolePolicyStatement($origin, DefaultRolePolicy::from($words[3]), $accounts);
        }
        if ($lists === []) {
            return new Statement\SetRolePolicyStatement($origin, SessionRolePolicy::from($words[2]));
        }
        $roles = AccountNames::read($lists[0], $identifiers);
        return ($words[2] ?? '') === 'ALL'
            ? new Statement\SetRolesExceptStatement($origin, $roles)
            : new Statement\SetExplicitRolesStatement($origin, $roles);
    }
}
