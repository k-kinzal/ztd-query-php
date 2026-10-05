<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\Identification;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * Lowers the authentication clauses of CREATE USER and ALTER USER (8.0+).
 *
 * Rule: MYSQL-ACCOUNT-IDENTIFICATION-001. Scope: identification,
 * identified_by_password, identified_by_random_password,
 * identified_with_plugin, identified_with_plugin_as_auth,
 * identified_with_plugin_by_password,
 * identified_with_plugin_by_random_password, opt_create_user_with_mfa,
 * opt_initial_auth, opt_replace_password, opt_retain_current_password,
 * opt_discard_old_password, factor. Each clause is one LEX_MFA: the plugin
 * and what is given to it. Passwords and authentication strings keep their
 * exact values. Constructs: Identification. Terminates: unit productions
 * over strict subtrees.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-authentication.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class IdentificationRule
{
    /**
     * The unit productions of identification.
     */
    private const FORWARD = [
        'identification: identified_by_password' => true, 'identification: identified_by_random_password' => true, 'identification: identified_with_plugin' => true,
        'identification: identified_with_plugin_as_auth' => true, 'identification: identified_with_plugin_by_password' => true,
        'identification: identified_with_plugin_by_random_password' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an identification or one of the identified_* rules.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function identification(Node $identification): Identification
    {
        $form = $this->lowering->form($identification);
        if ($form->node->name === 'identification') {
            if (!isset(self::FORWARD[$form->signature])) {
                throw ImplementationGap::production($form);
            }
            $form = $this->lowering->form($form->node(0));
        }
        $names = $this->lowering->names;
        $literals = $this->lowering->literals;

        return match ($form->signature) {
            'identified_by_password: IDENTIFIED_SYM BY TEXT_STRING_password' => new Identification(null, Credential::Password, $literals->text($form->node(2))),
            'identified_by_random_password: IDENTIFIED_SYM BY RANDOM_SYM PASSWORD' => new Identification(null, Credential::RandomPassword),
            'identified_with_plugin: IDENTIFIED_SYM WITH ident_or_text' => new Identification($names->identifier($form->node(2)), Credential::None),
            'identified_with_plugin_as_auth: IDENTIFIED_SYM WITH ident_or_text AS TEXT_STRING_hash' => new Identification($names->identifier($form->node(2)), Credential::Hash, $literals->text($form->node(4))),
            'identified_with_plugin_by_password: IDENTIFIED_SYM WITH ident_or_text BY TEXT_STRING_password' => new Identification($names->identifier($form->node(2)), Credential::Password, $literals->text($form->node(4))),
            'identified_with_plugin_by_random_password: IDENTIFIED_SYM WITH ident_or_text BY RANDOM_SYM PASSWORD' => new Identification($names->identifier($form->node(2)), Credential::RandomPassword),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the further factors of CREATE USER; an absent clause is empty.
     *
     * @return list<Identification>
     * @throws ImplementationGap When the production has no rule
     */
    public function factors(Node $factors): array
    {
        $form = $this->lowering->form($factors);

        return match ($form->signature) {
            'opt_create_user_with_mfa:' => [],
            'opt_create_user_with_mfa: AND_SYM identification' => [$this->identification($form->node(1))],
            'opt_create_user_with_mfa: AND_SYM identification AND_SYM identification' => [$this->identification($form->node(1)), $this->identification($form->node(3))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the INITIAL AUTHENTICATION clause of a passwordless account.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function initial(Node $initial): Identification
    {
        $form = $this->lowering->form($initial);
        if (!in_array($form->signature, [
            'opt_initial_auth: INITIAL_SYM AUTHENTICATION_SYM identified_by_random_password',
            'opt_initial_auth: INITIAL_SYM AUTHENTICATION_SYM identified_with_plugin_as_auth',
            'opt_initial_auth: INITIAL_SYM AUTHENTICATION_SYM identified_by_password',
        ], true)) {
            throw ImplementationGap::production($form);
        }

        return $this->identification($form->node(2));
    }

    /**
     * Lowers an opt_replace_password; an absent clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function replace(Node $replace): ?Text
    {
        $form = $this->lowering->form($replace);

        return match ($form->signature) {
            'opt_replace_password:' => null,
            'opt_replace_password: REPLACE_SYM TEXT_STRING_password' => $this->lowering->literals->text($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an opt_retain_current_password.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function retain(Node $retain): bool
    {
        $form = $this->lowering->form($retain);

        return match ($form->signature) {
            'opt_retain_current_password:' => false,
            'opt_retain_current_password: RETAIN_SYM CURRENT_SYM PASSWORD' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an opt_discard_old_password.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function discard(Node $discard): bool
    {
        $form = $this->lowering->form($discard);

        return match ($form->signature) {
            'opt_discard_old_password:' => false,
            'opt_discard_old_password: DISCARD_SYM OLD_SYM PASSWORD' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a factor: the number before FACTOR, as written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function factor(Node $factor): Numeral
    {
        $form = $this->lowering->form($factor);
        if ($form->signature !== 'factor: NUM FACTOR_SYM') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->numbers->token($form->token(0));
    }
}
