<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists;
use SqlSemantics\Platform\PostgreSql\Rules\Access\RoleChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to revoke privileges on objects, or only the right to grant them, from roles.
 *
 * Rule: PG-REVOKE-001. Mirrors `GrantStmt` without `is_grant`: the
 * privileges (an empty list is ALL PRIVILEGES), the objects, the roles,
 * GRANT OPTION FOR, GRANTED BY and CASCADE or RESTRICT as written. The
 * objects and privileges are derived and checked as for GRANT.
 * Source: https://www.postgresql.org/docs/17/sql-revoke.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of a revoke
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REVOKE GRANT OPTION FOR SELECT ON accounts FROM joe CASCADE');
 *     [$operation->statement->grantOptionOnly, $operation->statement->behavior?->value] // => [true, 'CASCADE']
 */
final class Revoke implements Statement
{
    use Snapshot;

    /**
     * @var list<Privilege> The privileges in the order written; empty for ALL PRIVILEGES
     */
    public readonly array $privileges;

    /**
     * @var non-empty-list<RoleSpec> The roles in the order written
     */
    public readonly array $grantees;

    /**
     * @param list<Privilege> $privileges The privileges in the order written; empty for ALL PRIVILEGES
     * @param PrivilegeTarget $target The objects the privileges are revoked on
     * @param list<RoleSpec> $grantees The roles the privileges are revoked from, at least one
     * @param bool $grantOptionOnly Whether GRANT OPTION FOR is written: only the right to grant is revoked
     * @param RoleSpec|null $grantor The role of GRANTED BY, when written
     * @param DropBehavior|null $behavior CASCADE or RESTRICT, when written
     */
    public function __construct(array $privileges, public readonly PrivilegeTarget $target, array $grantees, public readonly bool $grantOptionOnly = false, public readonly ?RoleSpec $grantor = null, public readonly ?DropBehavior $behavior = null)
    {
        $this->privileges = (new PrivilegeLists())->privileges($privileges);
        $this->grantees = Check::listOf($grantees, RoleSpec::class, 'A revoke names at least one role.', 1);
    }

    /**
     * Derives the objects and reports the problems of the privileges and the grantor.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new PrivilegeChecks())->privileges($derivation, $this->privileges, $this->target->object(), false);
        if ($this->grantor !== null) {
            (new RoleChecks())->existing($derivation, [$this->grantor]);
        }
        $this->target->deriveTarget($derivation, $this->privileges);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('REVOKE');
        if ($this->grantOptionOnly) {
            $out->keyword('GRANT', 'OPTION', 'FOR');
        }
        (new PrivilegeLists())->write($out, $this->privileges);
        $out->keyword('ON')->node($this->target)->keyword('FROM')->list($this->grantees);
        if ($this->grantor !== null) {
            $out->keyword('GRANTED', 'BY')->node($this->grantor);
        }
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
