<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Access;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\GrantRole;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipOption;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\MembershipSetting;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\RevokeRole;
use SqlSemantics\Statement\Statement;

/**
 * Lowers GRANT and REVOKE of role membership.
 *
 * Rule: PG-MEMBERSHIP-LOWER-001. Scope: `GrantRoleStmt`, `RevokeRoleStmt`,
 * `grant_role_opt_list`, `grant_role_opt`, `grant_role_opt_value`.
 * Constructors: `GrantRole`, `RevokeRole`, `MembershipOption`. The granted
 * roles are a `privilege_list` (PG-PRIVILEGE-LOWER-001). Termination: lists
 * are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html#SQL-GRANT-DESCRIPTION-ROLES, https://www.postgresql.org/docs/17/sql-revoke.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class MembershipRule
{
    /**
     * The value each `grant_role_opt_value` production spells.
     */
    private const SETTINGS = [
        'grant_role_opt_value: OPTION' => MembershipSetting::Option,
        'grant_role_opt_value: TRUE_P' => MembershipSetting::True,
        'grant_role_opt_value: FALSE_P' => MembershipSetting::False,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `GrantRoleStmt` or `RevokeRoleStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $parts = new PrivilegeRule($this->lowering);
        $roles = $this->lowering->roles;
        $flags = $this->lowering->flags;

        return match ($form->signature) {
            'GrantRoleStmt: GRANT privilege_list TO role_list opt_granted_by' => new GrantRole($parts->list($form->node(1)), $roles->roles($form->node(3)), [], $parts->grantor($form->node(4))),
            'GrantRoleStmt: GRANT privilege_list TO role_list WITH grant_role_opt_list opt_granted_by' => new GrantRole($parts->list($form->node(1)), $roles->roles($form->node(3)), $this->options($form->node(5)), $parts->grantor($form->node(6))),
            'RevokeRoleStmt: REVOKE privilege_list FROM role_list opt_granted_by opt_drop_behavior' => new RevokeRole($parts->list($form->node(1)), $roles->roles($form->node(3)), null, $parts->grantor($form->node(4)), $flags->dropBehavior($form->node(5))),
            'RevokeRoleStmt: REVOKE ColId OPTION FOR privilege_list FROM role_list opt_granted_by opt_drop_behavior' => new RevokeRole($parts->list($form->node(4)), $roles->roles($form->node(6)), $this->lowering->names->name($form->node(1)), $parts->grantor($form->node(7)), $flags->dropBehavior($form->node(8))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `grant_role_opt_list`: the membership options in order.
     *
     * @return list<MembershipOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function options(Node $list): array
    {
        $options = [];
        foreach ($this->lowering->items($list, 'grant_role_opt_list: grant_role_opt', 'grant_role_opt_list: grant_role_opt_list , grant_role_opt') as $item) {
            $form = $this->lowering->productions->form($item);
            if ($form->signature !== 'grant_role_opt: ColLabel grant_role_opt_value') {
                throw ImplementationGap::production($form);
            }
            $value = $this->lowering->productions->form($form->node(1));
            $options[] = new MembershipOption($this->lowering->names->name($form->node(0)), self::SETTINGS[$value->signature] ?? throw ImplementationGap::production($value));
        }

        return $options;
    }
}
