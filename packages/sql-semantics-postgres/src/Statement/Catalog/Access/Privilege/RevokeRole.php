<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\MembershipChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to remove roles from the membership of other roles, or to remove one membership option only.
 *
 * Rule: PG-REVOKE-ROLE-001. Mirrors `GrantRoleStmt` without `is_grant`: the
 * revoked roles, the member roles, the option of `name OPTION FOR` (the
 * membership stays and only that option is turned off), GRANTED BY and
 * CASCADE or RESTRICT as written. Checked as a role grant
 * (PG-MEMBERSHIP-CHECK-001).
 * Source: https://www.postgresql.org/docs/17/sql-revoke.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the option a revoke turns off
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REVOKE ADMIN OPTION FOR staff FROM joe');
 *     [$operation->statement->option?->value, $operation->statement->roles[0]->privilege()] // => ['admin', 'staff']
 */
final class RevokeRole implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Privilege> The revoked roles in the order written
     */
    public readonly array $roles;

    /**
     * @var non-empty-list<RoleSpec> The roles that lose the membership, in the order written
     */
    public readonly array $members;

    /**
     * @param list<Privilege> $roles The revoked roles in the order written, at least one
     * @param list<RoleSpec> $members The roles that lose the membership, at least one
     * @param Name|null $option The option of `name OPTION FOR`, when written
     * @param RoleSpec|null $grantor The role of GRANTED BY, when written
     * @param DropBehavior|null $behavior CASCADE or RESTRICT, when written
     */
    public function __construct(array $roles, array $members, public readonly ?Name $option = null, public readonly ?RoleSpec $grantor = null, public readonly ?DropBehavior $behavior = null)
    {
        $this->roles = (new PrivilegeLists())->roles($roles);
        $this->members = Check::listOf($members, RoleSpec::class, 'A role revoke names at least one member.', 1);
    }

    /**
     * Reports the problems of the roles and of the option.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $checks = new MembershipChecks();
        $checks->roles($derivation, $this->roles, $this->members, $this->grantor);
        if ($this->option !== null && !in_array($this->option->value, MembershipOption::RECOGNIZED, true)) {
            $checks->option($derivation, $this->option);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('REVOKE');
        if ($this->option !== null) {
            $out->name($this->option, NameUse::Column)->keyword('OPTION', 'FOR');
        }
        $out->list($this->roles)->keyword('FROM')->list($this->members);
        if ($this->grantor !== null) {
            $out->keyword('GRANTED', 'BY')->node($this->grantor);
        }
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
