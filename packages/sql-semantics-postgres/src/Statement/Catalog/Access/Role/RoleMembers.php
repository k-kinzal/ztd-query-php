<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A role option that lists other roles: ROLE, USER, ADMIN, IN ROLE or IN GROUP.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility public
 * @example Reading the roles a new role becomes a member of
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE joe IN ROLE staff, sales');
 *     [$operation->statement->options[0]->kind->value, count($operation->statement->options[0]->roles)] // => ['IN ROLE', 2]
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
