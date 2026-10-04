<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\MembershipChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to make roles members of other roles.
 *
 * Rule: PG-GRANT-ROLE-001. Mirrors `GrantRoleStmt` with `is_grant`: the
 * granted roles, the member roles, the membership options of WITH and
 * GRANTED BY. The grammar reads the granted roles as a privilege list, so a
 * granted role may carry a column list or be spelled with a privilege
 * keyword; a column list is reported, as are an option name the server does
 * not recognize and PUBLIC as a granted role, member or grantor
 * (PG-MEMBERSHIP-CHECK-001).
 * Source: https://www.postgresql.org/docs/17/sql-grant.html#SQL-GRANT-DESCRIPTION-ROLES. Status: Implemented.
 *
 * @visibility public
 * @example Reading a membership grant
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT staff TO joe WITH ADMIN OPTION GRANTED BY boss');
 *     [$operation->statement->roles[0]->privilege(), $operation->statement->options[0]->setting->value, $operation->statement->grantor?->name?->value] // => ['staff', 'OPTION', 'boss']
 */
final class GrantRole implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Privilege> The granted roles in the order written
     */
    public readonly array $roles;

    /**
     * @var non-empty-list<RoleSpec> The roles that become members, in the order written
     */
    public readonly array $members;

    /**
     * @var list<MembershipOption> The options of WITH in the order written
     */
    public readonly array $options;

    /**
     * @param list<Privilege> $roles The granted roles in the order written, at least one
     * @param list<RoleSpec> $members The roles that become members, at least one
     * @param list<MembershipOption> $options The options of WITH in the order written
     * @param RoleSpec|null $grantor The role of GRANTED BY, when written
     */
    public function __construct(array $roles, array $members, array $options = [], public readonly ?RoleSpec $grantor = null)
    {
        $this->roles = (new PrivilegeLists())->roles($roles);
        $this->members = Check::listOf($members, RoleSpec::class, 'A role grant names at least one member.', 1);
        $this->options = Check::listOf($options, MembershipOption::class, 'Membership options are a list of membership options.');
    }

    /**
     * Reports the problems of the roles and of the options.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $checks = new MembershipChecks();
        $checks->roles($derivation, $this->roles, $this->members, $this->grantor);
        foreach ($this->options as $option) {
            if (!$option->recognized()) {
                $checks->option($derivation, $option->name);
            }
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('GRANT')->list($this->roles)->keyword('TO')->list($this->members);
        if ($this->options !== []) {
            $out->keyword('WITH')->list($this->options);
        }
        if ($this->grantor !== null) {
            $out->keyword('GRANTED', 'BY')->node($this->grantor);
        }
    }
}
