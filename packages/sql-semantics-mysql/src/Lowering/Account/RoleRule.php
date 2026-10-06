<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\CreateRole;
use SqlSemantics\Platform\MySql\Statement\Account\DropRole;
use SqlSemantics\Platform\MySql\Statement\Account\SetDefaultRole;
use SqlSemantics\Platform\MySql\Statement\Account\SetRole;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the role statements and role lists (8.0+).
 *
 * Rule: MYSQL-ACCOUNT-ROLE-001. Scope: create_role_stmt, drop_role_stmt,
 * set_role_stmt, role_list, opt_except_role_list, opt_with_roles. A role
 * name has the two parts of an account name. Constructs: CreateRole,
 * DropRole, SetRole, SetDefaultRole, RoleSelection. Terminates: the role
 * list is flattened iteratively; every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/roles.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RoleRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers CREATE ROLE, DROP ROLE, SET ROLE and SET DEFAULT ROLE.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Form $form): Statement
    {
        return match ($form->signature) {
            'create_role_stmt: CREATE ROLE_SYM opt_if_not_exists role_list' => new CreateRole($this->lowering->options->present($form->node(2)), $this->roles($form->node(3))),
            'drop_role_stmt: DROP ROLE_SYM if_exists role_list' => new DropRole($this->lowering->options->present($form->node(2)), $this->roles($form->node(3))),
            'set_role_stmt: SET_SYM ROLE_SYM role_list' => new SetRole(new RoleSelection(RoleSet::Named, $this->roles($form->node(2)))),
            'set_role_stmt: SET_SYM ROLE_SYM NONE_SYM' => new SetRole(new RoleSelection(RoleSet::None)),
            'set_role_stmt: SET_SYM ROLE_SYM DEFAULT_SYM' => new SetRole(new RoleSelection(RoleSet::Default)),
            'set_role_stmt: SET_SYM ROLE_SYM ALL opt_except_role_list' => new SetRole(new RoleSelection(RoleSet::All, $this->except($form->node(3)))),
            'set_role_stmt: SET_SYM DEFAULT_SYM ROLE_SYM role_list TO_SYM role_list' => new SetDefaultRole(new RoleSelection(RoleSet::Named, $this->roles($form->node(3))), $this->roles($form->node(5))),
            'set_role_stmt: SET_SYM DEFAULT_SYM ROLE_SYM NONE_SYM TO_SYM role_list' => new SetDefaultRole(new RoleSelection(RoleSet::None), $this->roles($form->node(5))),
            'set_role_stmt: SET_SYM DEFAULT_SYM ROLE_SYM ALL TO_SYM role_list' => new SetDefaultRole(new RoleSelection(RoleSet::All), $this->roles($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a role_list.
     *
     * @return non-empty-list<AccountName>
     * @throws ImplementationGap When a production has no rule
     */
    public function roles(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature !== 'role_list: role' && $form->signature !== 'role_list: role_list , role') {
            throw ImplementationGap::production($form);
        }
        $roles = [];
        foreach ((new Lists())->items($list) as $item) {
            $role = $this->lowering->users->account($item);
            Check::invariant($role instanceof AccountName, 'A role is written as an account name.');
            $roles[] = $role;
        }
        Check::invariant($roles !== [], 'A role list names at least one role.');

        return $roles;
    }

    /**
     * Lowers an opt_except_role_list; an absent list is empty.
     *
     * @return list<AccountName>
     * @throws ImplementationGap When the production has no rule
     */
    public function except(Node $except): array
    {
        $form = $this->lowering->form($except);

        return match ($form->signature) {
            'opt_except_role_list:' => [],
            'opt_except_role_list: EXCEPT_SYM role_list' => $this->roles($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the opt_with_roles of GRANT … AS; an absent clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function withRoles(Node $clause): ?RoleSelection
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_with_roles:' => null,
            'opt_with_roles: WITH ROLE_SYM role_list' => new RoleSelection(RoleSet::Named, $this->roles($form->node(2))),
            'opt_with_roles: WITH ROLE_SYM ALL opt_except_role_list' => new RoleSelection(RoleSet::All, $this->except($form->node(3))),
            'opt_with_roles: WITH ROLE_SYM NONE_SYM' => new RoleSelection(RoleSet::None),
            'opt_with_roles: WITH ROLE_SYM DEFAULT_SYM' => new RoleSelection(RoleSet::Default),
            default => throw ImplementationGap::production($form),
        };
    }
}
