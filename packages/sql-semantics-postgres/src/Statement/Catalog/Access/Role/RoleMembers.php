<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;

/**
 * A role option that lists other roles: ROLE, USER, ADMIN, IN ROLE or IN GROUP.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility SqlSemantics
 */
final class RoleMembers implements RoleOption
{
    use Snapshot;

    /**
     * @var non-empty-list<RoleSpec> The listed roles in the order written
     */
    public readonly array $roles;

    /**
     * @param RoleMembersKind $kind The spelling, which tells what the list means
     * @param list<RoleSpec> $roles The listed roles in the order written, at least one
     */
    public function __construct(public readonly RoleMembersKind $kind, array $roles)
    {
        $this->roles = Check::listOf($roles, RoleSpec::class, 'A role list names at least one role.', 1);
    }

    /**
     * Answers the option the list fills.
     */
    public function option(): string
    {
        return $this->kind->option();
    }

    /**
     * Writes the keywords and the roles.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->kind->value))->list($this->roles);
    }
}
