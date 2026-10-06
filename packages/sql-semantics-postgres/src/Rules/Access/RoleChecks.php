<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleConnectionLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembers;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleOption;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RolePassword;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks the roles and role options of the role commands.
 *
 * Rule: PG-ROLE-CHECK-001. The checks need no declaration: roles are not
 * declarations a context holds, so whether a named role exists is never
 * reported. Reported, in the words of the server: an option of the server
 * filled twice ("conflicting or redundant options"; SYSID fills none), an
 * option word outside the attribute list, UNENCRYPTED PASSWORD, a
 * connection limit below -1, a role name starting with `pg_` where a role is
 * created or altered, PUBLIC where an existing role is required (PUBLIC is
 * not a role), and any designation other than a name in DROP ROLE.
 * CURRENT_ROLE, CURRENT_USER and SESSION_USER depend on the session and are
 * not checked. Termination: one pass over a finite list.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html, https://www.postgresql.org/docs/17/sql-alterrole.html,
 * https://www.postgresql.org/docs/17/sql-droprole.html, https://www.postgresql.org/docs/17/user-manag.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RoleChecks
{
    /**
     * Reports the problems of a role option list.
     *
     * @param list<RoleOption> $options
     */
    public function options(Derivation $derivation, array $options): void
    {
        $filled = [];
        foreach ($options as $option) {
            if ($option instanceof RoleAttribute && $option->flag() === null) {
                $derivation->report(new AccessProblem(AccessProblemRule::UnrecognizedRoleOption, [$option->word->value]));
            }
            if ($option instanceof RolePassword && $option->unencrypted) {
                $derivation->report(new AccessProblem(AccessProblemRule::UnencryptedPassword));
            }
            if ($option instanceof RoleConnectionLimit && !$option->acceptable()) {
                $derivation->report(new AccessProblem(AccessProblemRule::InvalidConnectionLimit, ['-' . $option->magnitude->digits]));
            }
            if ($option instanceof RoleMembers) {
                $this->existing($derivation, $option->roles);
            }
            if ($option->option() !== null) {
                $filled[] = $option->option();
            }
        }
        if (count(array_unique($filled)) !== count($filled)) {
            $derivation->report(new AccessProblem(AccessProblemRule::RedundantOptions));
        }
    }

    /**
     * Reports a role name of the reserved `pg_` prefix.
     */
    public function reserved(Derivation $derivation, Name $name): void
    {
        if (str_starts_with($name->value, 'pg_')) {
            $derivation->report(new AccessProblem(AccessProblemRule::ReservedRoleName, [$name->value]));
        }
    }

    /**
     * Reports a role that cannot be altered: PUBLIC, which is no role, or a reserved name.
     */
    public function alterable(Derivation $derivation, RoleSpec $role): void
    {
        $this->existing($derivation, [$role]);
        if ($role->name !== null) {
            $this->reserved($derivation, $role->name);
        }
    }

    /**
     * Reports PUBLIC where existing roles are required.
     *
     * @param list<RoleSpec> $roles
     */
    public function existing(Derivation $derivation, array $roles): void
    {
        foreach ($roles as $role) {
            if ($role->kind === RoleSpecKind::Everyone) {
                $derivation->report(new AccessProblem(AccessProblemRule::PublicRole));
            }
        }
    }

    /**
     * Reports every role of DROP ROLE that is not given by name.
     *
     * @param list<RoleSpec> $roles
     */
    public function droppable(Derivation $derivation, array $roles): void
    {
        foreach ($roles as $role) {
            if ($role->kind !== RoleSpecKind::Named) {
                $derivation->report(new AccessProblem(AccessProblemRule::SpecialRoleDrop));
            }
        }
    }
}
