<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;

/**
 * Lowers SET PASSWORD of every release.
 *
 * Rule: MYSQL-ACCOUNT-PASSWORD-001. Scope: the PASSWORD productions of
 * start_option_value_list (5.7, 8.0+) and option_value_no_option_type (5.6),
 * which the SET rules of the utility family hand over, and text_or_password
 * (5.6), password (5.7). The password keeps its exact value; `=` and `:=`
 * are the same request. Constructs: SetPassword, Password. Terminates:
 * every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-password.html,
 * https://dev.mysql.com/doc/refman/5.6/en/set-password.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PasswordRule
{
    /**
     * The SET PASSWORD productions, by the position of the account after FOR (or null) and of the password (or null for TO RANDOM).
     */
    private const FORMS = [
        'start_option_value_list: PASSWORD equal TEXT_STRING_password opt_replace_password opt_retain_current_password' => [null, 2],
        'start_option_value_list: PASSWORD TO_SYM RANDOM_SYM opt_replace_password opt_retain_current_password' => [null, null],
        'start_option_value_list: PASSWORD FOR_SYM user equal TEXT_STRING_password opt_replace_password opt_retain_current_password' => [2, 4],
        'start_option_value_list: PASSWORD FOR_SYM user TO_SYM RANDOM_SYM opt_replace_password opt_retain_current_password' => [2, null],
        'start_option_value_list: PASSWORD equal password' => [null, 2],
        'start_option_value_list: PASSWORD equal PASSWORD ( password )' => [null, 4],
        'start_option_value_list: PASSWORD FOR_SYM user equal password' => [2, 4],
        'start_option_value_list: PASSWORD FOR_SYM user equal PASSWORD ( password )' => [2, 6],
        'option_value_no_option_type: PASSWORD equal text_or_password' => [null, 2],
        'option_value_no_option_type: PASSWORD FOR_SYM user equal text_or_password' => [2, 4],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a SET PASSWORD production.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Form $form): SetPassword
    {
        if (!array_key_exists($form->signature, self::FORMS)) {
            throw ImplementationGap::production($form);
        }
        [$user, $position] = self::FORMS[$form->signature];
        $account = $user === null ? null : $this->lowering->users->account($form->node($user));
        $password = $position === null ? null : $this->password($form->node($position));
        if (str_ends_with($form->signature, 'PASSWORD ( password )') && $password !== null) {
            $password = new Password($password->text, PasswordFunction::Password);
        }
        if (!str_ends_with($form->signature, 'opt_replace_password opt_retain_current_password')) {
            return new SetPassword($account, $password);
        }
        $identifications = new IdentificationRule($this->lowering);
        $last = count($form->node->children) - 1;

        return new SetPassword($account, $password, $identifications->replace($form->node($last - 1)), $identifications->retain($form->node($last)));
    }

    /**
     * Lowers the password operand: a TEXT_STRING_password (8.0+), a password (5.7) or a text_or_password (5.6).
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function password(Node $password): Password
    {
        $form = $this->lowering->form($password);
        $clauses = new ClauseRule($this->lowering);

        return match ($form->signature) {
            'TEXT_STRING_password: TEXT_STRING' => new Password($this->lowering->literals->text($password)),
            'password: TEXT_STRING', 'text_or_password: TEXT_STRING' => new Password($clauses->string($form, 0)),
            'text_or_password: PASSWORD ( TEXT_STRING )' => new Password($clauses->string($form, 2), PasswordFunction::Password),
            'text_or_password: OLD_PASSWORD ( TEXT_STRING )' => new Password($clauses->string($form, 2), PasswordFunction::OldPassword),
            default => throw ImplementationGap::production($form),
        };
    }
}
