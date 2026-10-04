<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeAll;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Statement\Statement;

/**
 * Lowers GRANT and REVOKE of MySQL 8.0 and later.
 *
 * Rule: MYSQL-ACCOUNT-GRANT-001. Scope: grant, revoke (8.0+ productions),
 * opt_with_admin_option, opt_ignore_unknown_user, opt_grant_as. Without an
 * ON clause the list is a list of roles (PT_grant_roles, PT_revoke_roles);
 * with one it is a list of privileges. A user_list account is an account
 * specification without authentication. Constructs: GrantPrivileges,
 * GrantRoles, GrantProxy, RevokePrivileges, RevokeAll, RevokeRoles,
 * RevokeProxy, GrantAs. Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html,
 * https://dev.mysql.com/doc/refman/8.4/en/revoke.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GrantRule
{
    private readonly PrivilegeRule $privileges;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->privileges = new PrivilegeRule($lowering);
    }

    /**
     * Lowers an 8.0 grant production.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function grant(Form $form): Statement
    {
        $privileges = $this->privileges;
        $clauses = new ClauseRule($this->lowering);

        return match ($form->signature) {
            'grant: GRANT role_or_privilege_list TO_SYM user_list opt_with_admin_option' => new GrantRoles(
                $privileges->items($form->node(1), true),
                $this->lowering->users->accounts($form->node(3)),
                $this->admin($form->node(4)),
            ),
            'grant: GRANT role_or_privilege_list ON_SYM opt_acl_type grant_ident TO_SYM user_list grant_options opt_grant_as' => new GrantPrivileges(
                $privileges->items($form->node(1), false),
                $privileges->kind($form->node(3)),
                $privileges->level($form->node(4)),
                $this->specifications($form->node(6)),
                null,
                $clauses->grantOptions($form->node(7)),
                $this->grantAs($form->node(8)),
            ),
            'grant: GRANT ALL opt_privileges ON_SYM opt_acl_type grant_ident TO_SYM user_list grant_options opt_grant_as' => $this->all($form),
            'grant: GRANT PROXY_SYM ON_SYM user TO_SYM user_list opt_grant_option' => new GrantProxy(
                $this->lowering->users->account($form->node(3)),
                $this->specifications($form->node(5)),
                $clauses->grantOption($form->node(6)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `GRANT ALL [PRIVILEGES] ON …` of 8.0.
     */
    public function all(Form $form): GrantPrivileges
    {
        $this->privileges->words($form->node(2));
        $clauses = new ClauseRule($this->lowering);

        return new GrantPrivileges(
            [new AllPrivileges()],
            $this->privileges->kind($form->node(4)),
            $this->privileges->level($form->node(5)),
            $this->specifications($form->node(7)),
            null,
            $clauses->grantOptions($form->node(8)),
            $this->grantAs($form->node(9)),
        );
    }

    /**
     * Lowers an 8.0 revoke production.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function revoke(Form $form): Statement
    {
        $ifExists = $this->lowering->options->present($form->node(1));
        $privileges = $this->privileges;
        $last = count($form->node->children) - 1;
        $ignore = $this->ignore($form->node($last));

        return match ($form->signature) {
            'revoke: REVOKE if_exists role_or_privilege_list FROM user_list opt_ignore_unknown_user' => new RevokeRoles(
                $ifExists,
                $privileges->items($form->node(2), true),
                $this->lowering->users->accounts($form->node(4)),
                $ignore,
            ),
            'revoke: REVOKE if_exists role_or_privilege_list ON_SYM opt_acl_type grant_ident FROM user_list opt_ignore_unknown_user' => new RevokePrivileges(
                $ifExists,
                $privileges->items($form->node(2), false),
                $privileges->kind($form->node(4)),
                $privileges->level($form->node(5)),
                $this->specifications($form->node(7)),
                $ignore,
            ),
            'revoke: REVOKE if_exists ALL opt_privileges ON_SYM opt_acl_type grant_ident FROM user_list opt_ignore_unknown_user' => $this->revokeAll($form, $ifExists, $ignore),
            'revoke: REVOKE if_exists ALL opt_privileges , GRANT OPTION FROM user_list opt_ignore_unknown_user' => $this->everything($form, $ifExists, $ignore),
            'revoke: REVOKE if_exists PROXY_SYM ON_SYM user FROM user_list opt_ignore_unknown_user' => new RevokeProxy(
                $ifExists,
                $this->lowering->users->account($form->node(4)),
                $this->specifications($form->node(6)),
                $ignore,
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `REVOKE ALL [PRIVILEGES] ON …` of 8.0.
     */
    public function revokeAll(Form $form, bool $ifExists, bool $ignore): RevokePrivileges
    {
        $this->privileges->words($form->node(3));

        return new RevokePrivileges($ifExists, [new AllPrivileges()], $this->privileges->kind($form->node(5)), $this->privileges->level($form->node(6)), $this->specifications($form->node(8)), $ignore);
    }

    /**
     * Lowers `REVOKE ALL [PRIVILEGES], GRANT OPTION FROM …` of 8.0.
     */
    public function everything(Form $form, bool $ifExists, bool $ignore): RevokeAll
    {
        $this->privileges->words($form->node(3));

        return new RevokeAll($ifExists, $this->specifications($form->node(8)), $ignore);
    }

    /**
     * Lowers a user_list into account specifications without authentication.
     *
     * @return non-empty-list<UserSpecification>
     * @throws ImplementationGap When a production has no rule
     */
    public function specifications(Node $list): array
    {
        $specifications = [];
        foreach ($this->lowering->users->accounts($list) as $account) {
            $specifications[] = new UserSpecification($account);
        }
        Check::invariant($specifications !== [], 'An account list names at least one account.');

        return $specifications;
    }

    /**
     * Lowers an opt_with_admin_option.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function admin(Node $option): bool
    {
        $form = $this->lowering->form($option);

        return match ($form->signature) {
            'opt_with_admin_option:' => false,
            'opt_with_admin_option: WITH ADMIN_SYM OPTION' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an opt_ignore_unknown_user.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function ignore(Node $option): bool
    {
        $form = $this->lowering->form($option);

        return match ($form->signature) {
            'opt_ignore_unknown_user:' => false,
            'opt_ignore_unknown_user: IGNORE_SYM UNKNOWN_SYM USER' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an opt_grant_as; an absent clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function grantAs(Node $clause): ?GrantAs
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_grant_as:' => null,
            'opt_grant_as: AS user opt_with_roles' => new GrantAs($this->lowering->users->account($form->node(1)), (new RoleRule($this->lowering))->withRoles($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }
}
