<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks the grants and revokes of role membership.
 *
 * Rule: PG-MEMBERSHIP-CHECK-001. Reported, in the words of the server: a
 * column list on a granted role ("column names cannot be included in
 * GRANT/REVOKE ROLE"), a membership option other than `admin`, `inherit` and
 * `set` ("unrecognized role option"), and PUBLIC as a granted role, a member
 * or the grantor: PUBLIC is not a role, so no membership involves it.
 * Whether a named role exists is never reported: roles are not declarations
 * a context holds. Termination: one pass over finite lists.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html#SQL-GRANT-DESCRIPTION-ROLES,
 * https://www.postgresql.org/docs/17/sql-revoke.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class MembershipChecks
{
    /**
     * Reports the problems of the granted roles, the members and the grantor.
     *
     * @param list<Privilege> $roles
     * @param list<RoleSpec> $members
     */
    public function roles(Derivation $derivation, array $roles, array $members, ?RoleSpec $grantor): void
    {
        foreach ($roles as $role) {
            if ($role->columns !== []) {
                $derivation->report(new AccessProblem(AccessProblemRule::RoleColumns));
            }
            if ($role->privilege() === 'public') {
                $derivation->report(new AccessProblem(AccessProblemRule::PublicRole));
            }
        }
        (new RoleChecks())->existing($derivation, $grantor === null ? $members : [...$members, $grantor]);
    }

    /**
     * Reports a membership option name the server does not recognize.
     */
    public function option(Derivation $derivation, Name $name): void
    {
        $derivation->report(new AccessProblem(AccessProblemRule::UnrecognizedRoleOption, [$name->value]));
    }
}
