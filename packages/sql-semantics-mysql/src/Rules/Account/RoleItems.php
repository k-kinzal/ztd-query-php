<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Account;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Grantable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;

/**
 * Narrows the operands of GRANT and REVOKE role lists to their retained domain.
 *
 * ALL belongs to a privilege list. A name without columns is a role in this
 * position, while static privileges and names with columns retain their
 * role-or-privilege diagnostic during derivation.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RoleItems
{
    /**
     * @param list<Grantable> $roles The role-list operands in written order
     * @param string $emptyMessage The enclosing statement's missing-list diagnostic
     * @return non-empty-list<GrantedRole|DynamicPrivilege|StaticPrivilege>
     */
    public function checked(array $roles, string $emptyMessage): array
    {
        return array_map(static function (Grantable $role): GrantedRole|DynamicPrivilege|StaticPrivilege {
            Check::input($role instanceof GrantedRole || $role instanceof DynamicPrivilege || $role instanceof StaticPrivilege, 'A role list holds roles; a name without columns reads as a role.');
            Check::input(!($role instanceof DynamicPrivilege && $role->columns === []), 'A role list holds roles; a name without columns reads as a role.');

            return $role;
        }, Check::listOf($roles, Grantable::class, $emptyMessage, 1));
    }
}
