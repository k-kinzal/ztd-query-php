<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep;
use SqlSemantics\Platform\MySql\Statement\Account\User\Identification;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\Account;

/**
 * Lowers the account lists of CREATE USER, ALTER USER and MySQL 5.x GRANT.
 *
 * Rule: MYSQL-ACCOUNT-LIST-001. Scope: create_user_list, create_user,
 * alter_user_list, alter_user (8.0+), grant_list, grant_user (5.x). Each
 * entry is the account with what the statement sets for it, as LEX_USER
 * holds it; the multi-factor forms of ALTER USER are factor changes.
 * Constructs: UserSpecification, FactorChange, FactorStep. Terminates: the
 * lists are flattened iteratively; every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-user.html,
 * https://dev.mysql.com/doc/refman/5.7/en/grant.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class UserListRule
{
    /**
     * The alter_user productions that set an authentication method, by the position of REPLACE's string (or null).
     */
    private const CHANGES = [
        'alter_user: user identified_by_password REPLACE_SYM TEXT_STRING_password opt_retain_current_password' => 3,
        'alter_user: user identified_with_plugin_by_password REPLACE_SYM TEXT_STRING_password opt_retain_current_password' => 3,
        'alter_user: user identified_by_random_password REPLACE_SYM TEXT_STRING_password opt_retain_current_password' => 3,
        'alter_user: user identified_by_password opt_retain_current_password' => null,
        'alter_user: user identified_by_random_password opt_retain_current_password' => null,
        'alter_user: user identified_with_plugin_as_auth opt_retain_current_password' => null,
        'alter_user: user identified_with_plugin_by_password opt_retain_current_password' => null,
        'alter_user: user identified_with_plugin_by_random_password opt_retain_current_password' => null,
    ];

    /**
     * The alter_user productions that change factors, by the action and the number of factors.
     */
    private const FACTORS = [
        'alter_user: user ADD factor identification' => [FactorAction::Add, 1],
        'alter_user: user ADD factor identification ADD factor identification' => [FactorAction::Add, 2],
        'alter_user: user MODIFY_SYM factor identification' => [FactorAction::Modify, 1],
        'alter_user: user MODIFY_SYM factor identification MODIFY_SYM factor identification' => [FactorAction::Modify, 2],
        'alter_user: user DROP factor' => [FactorAction::Drop, 1],
        'alter_user: user DROP factor DROP factor' => [FactorAction::Drop, 2],
    ];

    /**
     * The list productions this rule flattens.
     */
    private const LISTS = [
        'create_user_list: create_user' => true, 'create_user_list: create_user_list , create_user' => true,
        'alter_user_list: alter_user' => true, 'alter_user_list: alter_user_list , alter_user' => true,
        'grant_list: grant_user' => true, 'grant_list: grant_list , grant_user' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     * @param IdentificationRule $identifications The authentication clause rules
     */
    public function __construct(private readonly Lowering $lowering, private readonly IdentificationRule $identifications)
    {
    }

    /**
     * Lowers a create_user_list.
     *
     * @return list<UserSpecification>
     * @throws ImplementationGap When a production has no rule
     */
    public function created(Node $list): array
    {
        $users = [];
        foreach ($this->spine($list) as $item) {
            $users[] = $this->create($item);
        }

        return $users;
    }

    /**
     * Lowers one create_user.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function create(Node $user): UserSpecification
    {
        $form = $this->lowering->form($user);
        $account = $this->lowering->users->account($form->node(0));

        return match ($form->signature) {
            'create_user: user identification opt_create_user_with_mfa' => new UserSpecification($account, $this->identifications->identification($form->node(1)), $this->identifications->factors($form->node(2))),
            'create_user: user identified_with_plugin opt_initial_auth' => new UserSpecification($account, $this->identifications->identification($form->node(1)), [], $this->identifications->initial($form->node(2))),
            'create_user: user opt_create_user_with_mfa' => new UserSpecification($account, null, $this->identifications->factors($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an 8.0 alter_user_list.
     *
     * @return list<UserAlteration>
     * @throws ImplementationGap When a production has no rule
     */
    public function altered(Node $list): array
    {
        $users = [];
        foreach ($this->spine($list) as $item) {
            $users[] = $this->alter($item);
        }

        return $users;
    }

    /**
     * Lowers one 8.0 alter_user.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alter(Node $user): UserAlteration
    {
        $form = $this->lowering->form($user);
        $account = $this->lowering->users->account($form->node(0));
        if (array_key_exists($form->signature, self::CHANGES)) {
            $replace = self::CHANGES[$form->signature];
            $identification = $this->identifications->identification($form->node(1));
            $text = $replace === null ? null : $this->lowering->literals->text($form->node($replace));

            return new UserSpecification($account, $identification, [], null, $text, $this->identifications->retain($form->node(count($form->node->children) - 1)));
        }
        if (isset(self::FACTORS[$form->signature])) {
            return $this->factors($form, $account);
        }

        return match ($form->signature) {
            'alter_user: user identified_with_plugin' => new UserSpecification($account, $this->identifications->identification($form->node(1))),
            'alter_user: user opt_discard_old_password' => new UserSpecification($account, null, [], null, null, false, $this->identifications->discard($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the factor change forms of alter_user.
     */
    public function factors(Form $form, Account $account): FactorChange
    {
        [$action, $count] = self::FACTORS[$form->signature];
        $width = $action === FactorAction::Drop ? 2 : 3;
        $steps = [];
        for ($index = 0; $index < $count; $index++) {
            $start = 1 + $index * $width;
            $factor = $this->identifications->factor($form->node($start + 1));
            $steps[] = new FactorStep($factor, $action === FactorAction::Drop ? null : $this->identifications->identification($form->node($start + 2)));
        }

        return new FactorChange($account, $action, $steps);
    }

    /**
     * Lowers a MySQL 5.x grant_list.
     *
     * @return non-empty-list<UserSpecification>
     * @throws ImplementationGap When a production has no rule
     */
    public function granted(Node $list): array
    {
        $users = [];
        foreach ($this->spine($list) as $item) {
            $users[] = $this->grant($item);
        }

        return $users;
    }

    /**
     * Lowers one MySQL 5.x grant_user.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function grant(Node $user): UserSpecification
    {
        $form = $this->lowering->form($user);
        $account = $this->lowering->users->account($form->node(0));
        $clauses = new ClauseRule($this->lowering);

        return new UserSpecification($account, match ($form->signature) {
            'grant_user: user' => null,
            'grant_user: user IDENTIFIED_SYM BY TEXT_STRING' => new Identification(null, Credential::Password, $clauses->string($form, 3)),
            'grant_user: user IDENTIFIED_SYM BY PASSWORD TEXT_STRING' => new Identification(null, Credential::PasswordHash, $clauses->string($form, 4)),
            'grant_user: user IDENTIFIED_SYM WITH ident_or_text' => new Identification($this->lowering->names->identifier($form->node(3)), Credential::None),
            'grant_user: user IDENTIFIED_SYM WITH ident_or_text AS TEXT_STRING_sys' => new Identification($this->lowering->names->identifier($form->node(3)), Credential::Hash, $this->lowering->literals->text($form->node(5))),
            'grant_user: user IDENTIFIED_SYM WITH ident_or_text BY TEXT_STRING_sys' => new Identification($this->lowering->names->identifier($form->node(3)), Credential::Password, $this->lowering->literals->text($form->node(5))),
            default => throw ImplementationGap::production($form),
        });
    }

    /**
     * Answers the items of a list production of this rule, in source order.
     *
     * @return non-empty-list<Node>
     * @throws ImplementationGap When the production has no rule
     */
    public function spine(Node $list): array
    {
        $form = $this->lowering->form($list);
        if (!isset(self::LISTS[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        $items = (new Lists())->items($list);
        Check::invariant($items !== [], 'A list production holds at least one item.');

        return $items;
    }
}
