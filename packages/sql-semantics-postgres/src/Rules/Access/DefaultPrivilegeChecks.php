<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultGrant;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultRevoke;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultScope;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\ForRoles;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\InSchemas;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule;

/**
 * Checks ALTER DEFAULT PRIVILEGES.
 *
 * Rule: PG-DEFAULT-PRIVILEGES-CHECK-001. The server keeps one option for the
 * schemas and one for the roles: IN SCHEMA or FOR ROLE given twice is
 * "conflicting or redundant options". The privileges are checked against
 * the kind of object without column lists (PG-PRIVILEGE-CHECK-001). Default
 * privileges for schemas cannot be limited to schemas. PUBLIC is not a role
 * whose future objects can be named, and cannot receive the right to grant.
 * Termination: one pass over finite lists.
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DefaultPrivilegeChecks
{
    /**
     * Reports the problems of the limiting clauses and of the action.
     *
     * @param list<DefaultScope> $scopes
     */
    public function check(Derivation $derivation, array $scopes, DefaultGrant|DefaultRevoke $action): void
    {
        $filled = [];
        foreach ($scopes as $scope) {
            $filled[] = $scope->option();
            if ($scope instanceof ForRoles) {
                (new RoleChecks())->existing($derivation, $scope->roles);
            }
            if ($scope instanceof InSchemas && $action->objects === DefaultObjectKind::Schemas) {
                $derivation->report(new AccessProblem(AccessProblemRule::DefaultSchemasInSchema));
            }
        }
        if (count(array_unique($filled)) !== count($filled)) {
            $derivation->report(new AccessProblem(AccessProblemRule::RedundantOptions));
        }
        $checks = new PrivilegeChecks();
        $checks->privileges($derivation, $action->privileges, $action->objects->object(), true);
        if ($action instanceof DefaultGrant && $action->grantOption) {
            $checks->grantOption($derivation, $action->grantees);
        }
    }
}
