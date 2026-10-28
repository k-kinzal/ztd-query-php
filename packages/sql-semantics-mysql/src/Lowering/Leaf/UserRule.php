<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;

/**
 * Lowers account names: users, roles and definers.
 *
 * Rule: MYSQL-ACCOUNT-NAME-001. Scope: user, user_ident_or_text, role,
 * user_list, definer, definer_opt. An account is a user part and an optional
 * host part, each an identifier, a string or a host name word; or the
 * keyword CURRENT_USER, with or without parentheses. Constructs:
 * AccountName, CurrentUser. Terminates: the user list is flattened
 * iteratively; every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-names.html,
 * https://dev.mysql.com/doc/refman/8.4/en/role-names.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class UserRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one account.
     *
     * @throws ImplementationGap When the production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When an account operand exceeds its release's domain
     */
    public function account(Node $account): Account
    {
        $form = $this->lowering->productions->form($account);
        if ($form->signature === 'user: user_ident_or_text') {
            $form = $this->lowering->productions->form($form->node(0));
        }
        $names = $this->lowering->names;

        return $this->lowering->leaves->record(match ($form->signature) {
            'user: ident_or_text', 'user_ident_or_text: ident_or_text', 'role: role_ident_or_text' => $this->named($names->identifier($form->node(0)), null, $form->node),
            'user: ident_or_text @ ident_or_text', 'user_ident_or_text: ident_or_text @ ident_or_text', 'role: role_ident_or_text @ ident_or_text' => $this->named($names->identifier($form->node(0)), $names->identifier($form->node(2)), $form->node),
            'user: CURRENT_USER optional_braces' => new CurrentUser($this->lowering->options->present($form->node(1)) ? OptionalWords::Written : OptionalWords::Omitted),
            default => throw ImplementationGap::production($form),
        });
    }

    /**
     * Publishes an account only after checking the current language's operand limits.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When an account operand exceeds its release's domain
     */
    public function named(\SqlSemantics\Statement\Identifier\Name $user, ?\SqlSemantics\Statement\Identifier\Name $host, ?Node $source = null): AccountName
    {
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->problem($user, $host, $this->lowering->profile->grammar);
        if ($problem !== null) {
            throw new \SqlSemantics\Construction\RejectedInput($problem, $source === null ? 0 : ((new \SqlSemantics\Construction\SourceRanges())->span($source)[1] ?? 0));
        }

        $form = $source === null ? null : $this->lowering->form($source);
        return new AccountName(
            $user,
            $host,
            userString: $form !== null && in_array($this->lowering->names->identifierForm($form->node(0))->node->name, ['TEXT_STRING_sys', 'TEXT_STRING_validated'], true),
            hostString: $form !== null && $host !== null && in_array($this->lowering->names->identifierForm($form->node(2))->node->name, ['TEXT_STRING_sys', 'TEXT_STRING_validated'], true),
        );
    }

    /**
     * Lowers a comma-separated list of accounts.
     *
     * @return list<Account>
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When a decoded account exceeds the language's limits
     */
    public function accounts(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature !== 'user_list: user' && $form->signature !== 'user_list: user_list , user') {
            throw ImplementationGap::production($form);
        }
        $accounts = [];
        foreach ((new Lists())->items($list) as $item) {
            $accounts[] = $this->account($item);
        }

        return $accounts;
    }

    /**
     * Lowers a DEFINER clause; an absent clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function definer(Node $definer): ?Account
    {
        $form = $this->lowering->productions->form($definer);
        if ($form->signature === 'definer_opt: definer') {
            $form = $this->lowering->productions->form($form->node(0));
        }

        return match ($form->signature) {
            'definer_opt: no_definer' => null,
            'definer: DEFINER_SYM EQ user' => $this->account($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }
}
