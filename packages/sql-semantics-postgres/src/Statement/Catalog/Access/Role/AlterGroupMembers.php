<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Access\RoleChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AddOrDrop;

/**
 * A request to add members to a role or remove them, written as ALTER GROUP ... ADD USER or DROP USER.
 *
 * Rule: PG-ROLE-GROUP-001. Mirrors `AlterRoleStmt` with action +1 (ADD) or
 * -1 (DROP) and the single option `rolemembers`. Reported: PUBLIC as the
 * group or as a member, and a `pg_` name as the group.
 * Source: https://www.postgresql.org/docs/17/sql-altergroup.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterGroupMembers implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<RoleSpec> The members in the order written
     */
    public readonly array $members;

    /**
     * @param RoleSpec $group The role whose members change
     * @param AddOrDrop $action Whether the members are added or removed
     * @param list<RoleSpec> $members The members in the order written, at least one
     */
    public function __construct(public readonly RoleSpec $group, public readonly AddOrDrop $action, array $members)
    {
        $this->members = Check::listOf($members, RoleSpec::class, 'A role list names at least one role.', 1);
    }

    /**
     * Reports the problems of the group and of the members.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $checks = new RoleChecks();
        $checks->alterable($derivation, $this->group);
        $checks->existing($derivation, $this->members);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'GROUP')->node($this->group)->keyword($this->action->value, 'USER')->list($this->members);
    }
}
