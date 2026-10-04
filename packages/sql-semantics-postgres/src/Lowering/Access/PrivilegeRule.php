<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Access;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;

/**
 * Lowers the parts GRANT, REVOKE and ALTER DEFAULT PRIVILEGES share: privileges, grantees, the grant option and the grantor.
 *
 * Rule: PG-PRIVILEGE-LOWER-001. Scope: `privileges`, `privilege_list`,
 * `privilege`, `grantee_list`, `grantee`, `opt_grant_grant_option`,
 * `opt_granted_by`. Constructor: `Privilege`. ALL and ALL PRIVILEGES are an
 * empty list, as in the server; with a column list they are one privilege
 * without a name. PRIVILEGES after ALL and GROUP before a grantee are noise
 * (AccessNoise). Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html, https://www.postgresql.org/docs/17/sql-revoke.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class PrivilegeRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `privileges`: an empty list for ALL PRIVILEGES.
     *
     * @return list<Privilege>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function privileges(Node $privileges): array
    {
        $form = $this->lowering->productions->form($privileges);

        return match ($form->signature) {
            'privileges: privilege_list' => $this->list($form->node(0)),
            'privileges: ALL', 'privileges: ALL PRIVILEGES' => [],
            'privileges: ALL ( columnList )' => [new Privilege(PrivilegeKeyword::All, $this->lowering->names->names($form->node(2)))],
            'privileges: ALL PRIVILEGES ( columnList )' => [new Privilege(PrivilegeKeyword::All, $this->lowering->names->names($form->node(3)))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `privilege_list`: the privileges, or the roles of a role grant, in order.
     *
     * @return list<Privilege>
     */
    public function list(Node $list): array
    {
        $privileges = [];
        foreach ($this->lowering->items($list, 'privilege_list: privilege', 'privilege_list: privilege_list , privilege') as $item) {
            $privileges[] = $this->privilege($item);
        }

        return $privileges;
    }

    /**
     * Lowers `privilege`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function privilege(Node $privilege): Privilege
    {
        $form = $this->lowering->productions->form($privilege);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'privilege: SELECT opt_column_list' => new Privilege(PrivilegeKeyword::Select, $names->names($form->node(1))),
            'privilege: REFERENCES opt_column_list' => new Privilege(PrivilegeKeyword::References, $names->names($form->node(1))),
            'privilege: CREATE opt_column_list' => new Privilege(PrivilegeKeyword::Create, $names->names($form->node(1))),
            'privilege: ALTER SYSTEM_P' => new Privilege(PrivilegeKeyword::AlterSystem),
            'privilege: ColId opt_column_list' => new Privilege($names->name($form->node(0)), $names->names($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `grantee_list`; the word GROUP before a grantee is noise.
     *
     * @return list<RoleSpec>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function grantees(Node $list): array
    {
        $grantees = [];
        foreach ($this->lowering->items($list, 'grantee_list: grantee', 'grantee_list: grantee_list , grantee') as $item) {
            $form = $this->lowering->productions->form($item);
            $grantees[] = match ($form->signature) {
                'grantee: RoleSpec' => $this->lowering->roles->role($form->node(0)),
                'grantee: GROUP_P RoleSpec' => $this->lowering->roles->role($form->node(1)),
                default => throw ImplementationGap::production($form),
            };
        }

        return $grantees;
    }

    /**
     * Lowers `opt_grant_grant_option`: whether WITH GRANT OPTION is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function grantOption(Node $option): bool
    {
        $form = $this->lowering->productions->form($option);

        return match ($form->signature) {
            'opt_grant_grant_option: WITH GRANT OPTION' => true,
            'opt_grant_grant_option:' => false,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_granted_by`; no grantor is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function grantor(Node $grantor): ?RoleSpec
    {
        $form = $this->lowering->productions->form($grantor);

        return match ($form->signature) {
            'opt_granted_by: GRANTED BY RoleSpec' => $this->lowering->roles->role($form->node(2)),
            'opt_granted_by:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
