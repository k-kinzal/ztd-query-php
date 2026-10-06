<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Access;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword;
use SqlSemantics\Rendering\Output;

/**
 * Validates and writes the privilege list of GRANT and REVOKE.
 *
 * Rule: PG-PRIVILEGE-LIST-001. Scope: `privileges`, `privilege_list`. The
 * server keeps ALL PRIVILEGES as an empty list and ALL with a column list
 * as one privilege without a name; the grammar offers ALL only alone, so a
 * list that holds ALL holds nothing else. The roles of a role grant are a
 * `privilege_list`, which has no ALL and at least one item. Termination: one
 * pass over a finite list. Source: https://www.postgresql.org/docs/17/sql-grant.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class PrivilegeLists
{
    /**
     * Narrows a constructor argument to the privilege list of a grant on objects.
     *
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $privileges
     *
     * @return list<Privilege>
     *
     * @throws InvalidConstruction When the argument is no privilege list the grammar can write
     */
    public function privileges(array $privileges): array
    {
        $list = Check::listOf($privileges, Privilege::class, 'The privileges of a grant are a list of privileges.');
        foreach ($list as $privilege) {
            Check::input($privilege->name !== PrivilegeKeyword::All || count($list) === 1, 'ALL with a column list is the only privilege of its list.');
        }

        return $list;
    }

    /**
     * Narrows a constructor argument to the role list of a role grant.
     *
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $roles
     *
     * @return non-empty-list<Privilege>
     *
     * @throws InvalidConstruction When the argument is no role list the grammar can write
     */
    public function roles(array $roles): array
    {
        $list = Check::listOf($roles, Privilege::class, 'A role grant names at least one role.', 1);
        foreach ($list as $role) {
            Check::input($role->name !== PrivilegeKeyword::All, 'ALL is no role of a role grant.');
        }

        return $list;
    }

    /**
     * Writes the privilege list of a grant on objects: ALL when it is empty.
     *
     * @param list<Privilege> $privileges
     */
    public function write(Output $out, array $privileges): void
    {
        if ($privileges === []) {
            $out->keyword('ALL');
        }
        $out->list($privileges);
    }
}
