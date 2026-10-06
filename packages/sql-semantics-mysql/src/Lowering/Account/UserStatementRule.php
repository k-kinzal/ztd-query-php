<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole;
use SqlSemantics\Platform\MySql\Statement\Account\AlterRegistration;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;
use SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\Identification;
use SqlSemantics\Platform\MySql\Statement\Account\User\RegistrationStep;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet;
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Statement;

/**
 * Lowers CREATE USER, ALTER USER, DROP USER and RENAME USER of every release.
 *
 * Rule: MYSQL-ACCOUNT-USER-001. Scope: the `create:`, `alter:` and `drop:`
 * productions MYSQL-DEFINITION-ROUTES-001 routes here (5.x), alter_user_stmt,
 * alter_user_command, drop_user_stmt (8.0+), alter_user_list of 5.6,
 * rename_list, user_func, opt_user_registration. The marker
 * clear_privileges holds no operand. Constructs: CreateUser, AlterUser,
 * ExpireUserPasswords, AlterDefaultRole, AlterRegistration, DropUser,
 * RenameUser, SessionUser. Terminates: the lists are flattened iteratively;
 * every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-management-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class UserStatementRule
{
    private readonly ClauseRule $clauses;

    private readonly IdentificationRule $identifications;

    private readonly UserListRule $lists;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->clauses = new ClauseRule($lowering);
        $this->identifications = new IdentificationRule($lowering);
        $this->lists = new UserListRule($lowering, $this->identifications);
    }

    /**
     * Lowers a routed `create:`, `alter:` or `drop:` production of an account statement.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function definition(Form $form): Statement
    {
        $options = $this->lowering->options;
        $clauses = $this->clauses;

        return match ($form->signature) {
            'create: CREATE USER clear_privileges grant_list' => $this->skipped($form, 2, new CreateUser(false, $this->lists->granted($form->node(3)))),
            'create: CREATE USER opt_if_not_exists clear_privileges grant_list require_clause connect_options opt_account_lock_password_expire_options' => $this->skipped($form, 3, new CreateUser(
                $options->present($form->node(2)),
                $this->lists->granted($form->node(4)),
                [],
                $clauses->tls($form->node(5)),
                $clauses->limits($form->node(6)),
                $clauses->options($form->node(7)),
            )),
            'create: CREATE USER opt_if_not_exists create_user_list default_role_clause require_clause connect_options opt_account_lock_password_expire_options opt_user_attribute' => new CreateUser(
                $options->present($form->node(2)),
                $this->lists->created($form->node(3)),
                $this->defaultRoles($form->node(4)),
                $clauses->tls($form->node(5)),
                $clauses->limits($form->node(6)),
                $clauses->options($form->node(7)),
                $clauses->comment($form->node(8)),
            ),
            'alter: ALTER USER clear_privileges alter_user_list' => $this->skipped($form, 2, new ExpireUserPasswords($this->expired($form->node(3)))),
            'alter: alter_user_command grant_list require_clause connect_options opt_account_lock_password_expire_options' => new AlterUser(
                $this->command($form->node(0)),
                $this->lists->granted($form->node(1)),
                $clauses->tls($form->node(2)),
                $clauses->limits($form->node(3)),
                $clauses->options($form->node(4)),
            ),
            'alter: alter_user_command user_func IDENTIFIED_SYM BY TEXT_STRING' => new AlterUser($this->command($form->node(0)), [
                new UserSpecification($this->session($form->node(1)), new Identification(null, Credential::Password, $clauses->string($form, 4))),
            ]),
            'drop: DROP USER clear_privileges user_list' => $this->skipped($form, 2, new DropUser(false, $this->lowering->users->accounts($form->node(3)))),
            'drop: DROP USER if_exists clear_privileges user_list' => $this->skipped($form, 3, new DropUser($options->present($form->node(2)), $this->lowering->users->accounts($form->node(4)))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Confirms the clear_privileges marker at a position and answers the statement.
     */
    public function skipped(Form $form, int $marker, Statement $statement): Statement
    {
        $this->lowering->options->skip($form->node($marker));

        return $statement;
    }

    /**
     * Lowers an 8.0 alter_user_stmt.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alter(Form $form): Statement
    {
        $ifExists = $this->command($form->node(0));
        $clauses = $this->clauses;
        $identifications = $this->identifications;

        return match ($form->signature) {
            'alter_user_stmt: alter_user_command alter_user_list require_clause connect_options opt_account_lock_password_expire_options opt_user_attribute' => new AlterUser(
                $ifExists,
                $this->lists->altered($form->node(1)),
                $clauses->tls($form->node(2)),
                $clauses->limits($form->node(3)),
                $clauses->options($form->node(4)),
                $clauses->comment($form->node(5)),
            ),
            'alter_user_stmt: alter_user_command user_func identified_by_random_password opt_replace_password opt_retain_current_password',
            'alter_user_stmt: alter_user_command user_func identified_by_password opt_replace_password opt_retain_current_password' => new AlterUser($ifExists, [new UserSpecification(
                $this->session($form->node(1)),
                $identifications->identification($form->node(2)),
                [],
                null,
                $identifications->replace($form->node(3)),
                $identifications->retain($form->node(4)),
            )]),
            'alter_user_stmt: alter_user_command user_func DISCARD_SYM OLD_SYM PASSWORD' => new AlterUser($ifExists, [new UserSpecification($this->session($form->node(1)), null, [], null, null, false, true)]),
            'alter_user_stmt: alter_user_command user DEFAULT_SYM ROLE_SYM ALL' => new AlterDefaultRole($ifExists, $this->lowering->users->account($form->node(1)), new RoleSelection(RoleSet::All)),
            'alter_user_stmt: alter_user_command user DEFAULT_SYM ROLE_SYM NONE_SYM' => new AlterDefaultRole($ifExists, $this->lowering->users->account($form->node(1)), new RoleSelection(RoleSet::None)),
            'alter_user_stmt: alter_user_command user DEFAULT_SYM ROLE_SYM role_list' => new AlterDefaultRole(
                $ifExists,
                $this->lowering->users->account($form->node(1)),
                new RoleSelection(RoleSet::Named, (new RoleRule($this->lowering))->roles($form->node(4))),
            ),
            'alter_user_stmt: alter_user_command user opt_user_registration' => $this->registration($ifExists, $this->lowering->users->account($form->node(1)), $form->node(2)),
            'alter_user_stmt: alter_user_command user_func opt_user_registration' => $this->registration($ifExists, $this->session($form->node(1)), $form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an alter_user_command: whether IF EXISTS is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function command(Node $command): bool
    {
        $form = $this->lowering->form($command);
        if ($form->signature === 'alter_user_command: ALTER USER if_exists clear_privileges') {
            $this->lowering->options->skip($form->node(3));
        } elseif ($form->signature !== 'alter_user_command: ALTER USER if_exists') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->options->present($form->node(2));
    }

    /**
     * Lowers a user_func: `USER()`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function session(Node $function): SessionUser
    {
        $form = $this->lowering->form($function);
        if ($form->signature !== 'user_func: USER ( )') {
            throw ImplementationGap::production($form);
        }

        return new SessionUser();
    }

    /**
     * Lowers an opt_user_registration of an account.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function registration(bool $ifExists, Account|SessionUser $user, Node $registration): AlterRegistration
    {
        $form = $this->lowering->form($registration);
        $factor = $this->identifications->factor($form->node(0));

        return match ($form->signature) {
            'opt_user_registration: factor INITIATE_SYM REGISTRATION_SYM' => new AlterRegistration($ifExists, $user, $factor, RegistrationStep::Initiate),
            'opt_user_registration: factor UNREGISTER_SYM' => new AlterRegistration($ifExists, $user, $factor, RegistrationStep::Unregister),
            'opt_user_registration: factor FINISH_SYM REGISTRATION_SYM SET_SYM CHALLENGE_RESPONSE_SYM AS TEXT_STRING_hash' => new AlterRegistration(
                $ifExists,
                $user,
                $factor,
                RegistrationStep::Finish,
                $this->lowering->literals->text($form->node(6)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the DEFAULT ROLE clause of CREATE USER; an absent clause is empty.
     *
     * @return list<AccountName>
     * @throws ImplementationGap When the production has no rule
     */
    public function defaultRoles(Node $clause): array
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'default_role_clause:' => [],
            'default_role_clause: DEFAULT_SYM ROLE_SYM role_list' => (new RoleRule($this->lowering))->roles($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the MySQL 5.6 alter_user_list: accounts each followed by PASSWORD EXPIRE.
     *
     * @return list<Account>
     * @throws ImplementationGap When a production has no rule
     */
    public function expired(Node $list): array
    {
        $accounts = [];
        $node = $list;
        while (true) {
            $form = $this->lowering->form($node);
            if ($form->signature === 'alter_user_list: user PASSWORD EXPIRE_SYM') {
                array_unshift($accounts, $this->lowering->users->account($form->node(0)));

                return $accounts;
            }
            if ($form->signature !== 'alter_user_list: alter_user_list , user PASSWORD EXPIRE_SYM') {
                throw ImplementationGap::production($form);
            }
            array_unshift($accounts, $this->lowering->users->account($form->node(2)));
            $node = $form->node(0);
        }
    }

    /**
     * Lowers DROP USER (8.0+).
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function drop(Form $form): DropUser
    {
        if ($form->signature !== 'drop_user_stmt: DROP USER if_exists user_list') {
            throw ImplementationGap::production($form);
        }

        return new DropUser($this->lowering->options->present($form->node(2)), $this->lowering->users->accounts($form->node(3)));
    }

    /**
     * Lowers the rename_list of RENAME USER.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function rename(Node $list): RenameUser
    {
        $form = $this->lowering->form($list);
        if ($form->signature !== 'rename_list: user TO_SYM user' && $form->signature !== 'rename_list: rename_list , user TO_SYM user') {
            throw ImplementationGap::production($form);
        }
        $users = (new Lists())->items($list);
        $renamings = [];
        for ($index = 0; $index + 1 < count($users); $index += 2) {
            $renamings[] = new UserRenaming($this->lowering->users->account($users[$index]), $this->lowering->users->account($users[$index + 1]));
        }

        return new RenameUser($renamings);
    }
}
