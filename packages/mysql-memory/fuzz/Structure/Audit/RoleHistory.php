<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Scalar;

/**
 * Establishes referenced accounts and role grants without replacing the input.
 * Setup observations are compared in full before repeating the same structure.
 */
final class RoleHistory
{
    /**
     * @return list<M\Statement> Account and grant setup for a missing role dependency
     */
    public function steps(M\Statement|Scalar $node, int $code): array
    {
        if ($code === 1396 && ($node instanceof M\Account\DropRole || ($node instanceof M\Account\CreateRole && $node->ifNotExists))) {
            return [new M\Account\CreateUser(true, array_map(static fn (M\Name\AccountName $role): M\Account\User\UserSpecification => new M\Account\User\UserSpecification($role), $node->roles))];
        }
        if ($code === 3523 && $node instanceof M\Account\Privilege\GrantRoles) {
            return [
                new M\Account\CreateUser(true, array_map(static fn (M\Account\Privilege\Item\GrantedRole $role): M\Account\User\UserSpecification => new M\Account\User\UserSpecification($role->role), $node->roles)),
                new M\Account\CreateUser(true, array_map(static fn (M\Name\Account $user): M\Account\User\UserSpecification => new M\Account\User\UserSpecification($user), $node->users)),
            ];
        }
        if (!$node instanceof M\Account\AlterDefaultRole || !in_array($code, [3523, 3530], true)) {
            return [];
        }
        $steps = [new M\Account\CreateUser(true, [new M\Account\User\UserSpecification($node->user)])];
        if ($node->roles->roles !== []) {
            $steps[] = new M\Account\CreateUser(true, array_map(static fn (M\Name\AccountName $role): M\Account\User\UserSpecification => new M\Account\User\UserSpecification($role), $node->roles->roles));
            $steps[] = new M\Account\Privilege\GrantRoles(array_map(static fn (M\Name\AccountName $role): M\Account\Privilege\Item\GrantedRole => new M\Account\Privilege\Item\GrantedRole($role), $node->roles->roles), [$node->user]);
        }
        return $steps;
    }
}
