<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsCondition;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement;
use SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\WithOption;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * Lowers the account clauses shared by CREATE USER, ALTER USER and GRANT.
 *
 * Rule: MYSQL-ACCOUNT-CLAUSE-001. Scope: require_clause, require_list,
 * require_list_element, opt_and, connect_options, connect_option_list,
 * connect_option, grant_options, grant_option_list, grant_option,
 * opt_grant_option, opt_account_lock_password_expire_options,
 * opt_account_lock_password_expire_option_list,
 * opt_account_lock_password_expire_option, password_expire,
 * opt_user_attribute. Every clause keeps its options in source order and
 * every number and string its exact value; AND between REQUIRE conditions is
 * an optional word. Constructs: TlsRequirement, TlsCondition, ResourceLimit,
 * GrantOptionRight, AccountOption, UserComment. Terminates: the lists are
 * flattened iteratively; every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html,
 * https://dev.mysql.com/doc/refman/5.7/en/grant.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ClauseRule
{
    /**
     * The password and locking option productions that take no number, by the option they write.
     */
    private const OPTIONS = [
        'opt_account_lock_password_expire_option: ACCOUNT_SYM UNLOCK_SYM' => AccountOptionKind::AccountUnlock,
        'opt_account_lock_password_expire_option: ACCOUNT_SYM LOCK_SYM' => AccountOptionKind::AccountLock,
        'opt_account_lock_password_expire_option: PASSWORD EXPIRE_SYM' => AccountOptionKind::ExpireNow,
        'opt_account_lock_password_expire_option: password_expire' => AccountOptionKind::ExpireNow,
        'opt_account_lock_password_expire_option: PASSWORD EXPIRE_SYM NEVER_SYM' => AccountOptionKind::ExpireNever,
        'opt_account_lock_password_expire_option: password_expire NEVER_SYM' => AccountOptionKind::ExpireNever,
        'opt_account_lock_password_expire_option: PASSWORD EXPIRE_SYM DEFAULT_SYM' => AccountOptionKind::ExpireDefault,
        'opt_account_lock_password_expire_option: password_expire DEFAULT' => AccountOptionKind::ExpireDefault,
        'opt_account_lock_password_expire_option: PASSWORD HISTORY_SYM DEFAULT_SYM' => AccountOptionKind::HistoryDefault,
        'opt_account_lock_password_expire_option: PASSWORD REUSE_SYM INTERVAL_SYM DEFAULT_SYM' => AccountOptionKind::ReuseDefault,
        'opt_account_lock_password_expire_option: PASSWORD REQUIRE_SYM CURRENT_SYM' => AccountOptionKind::RequireCurrent,
        'opt_account_lock_password_expire_option: PASSWORD REQUIRE_SYM CURRENT_SYM DEFAULT_SYM' => AccountOptionKind::RequireCurrentDefault,
        'opt_account_lock_password_expire_option: PASSWORD REQUIRE_SYM CURRENT_SYM OPTIONAL_SYM' => AccountOptionKind::RequireCurrentOptional,
        'opt_account_lock_password_expire_option: PASSWORD_LOCK_TIME_SYM UNBOUNDED_SYM' => AccountOptionKind::LockTimeUnbounded,
    ];

    /**
     * The password and locking option productions that take a number, by the option and the position of the number.
     */
    private const NUMBERED = [
        'opt_account_lock_password_expire_option: PASSWORD EXPIRE_SYM INTERVAL_SYM real_ulong_num DAY_SYM' => [AccountOptionKind::ExpireInterval, 3],
        'opt_account_lock_password_expire_option: password_expire INTERVAL_SYM real_ulong_num DAY_SYM' => [AccountOptionKind::ExpireInterval, 2],
        'opt_account_lock_password_expire_option: PASSWORD HISTORY_SYM real_ulong_num' => [AccountOptionKind::HistoryCount, 2],
        'opt_account_lock_password_expire_option: PASSWORD REUSE_SYM INTERVAL_SYM real_ulong_num DAY_SYM' => [AccountOptionKind::ReuseInterval, 3],
        'opt_account_lock_password_expire_option: FAILED_LOGIN_ATTEMPTS_SYM real_ulong_num' => [AccountOptionKind::FailedLoginAttempts, 1],
        'opt_account_lock_password_expire_option: PASSWORD_LOCK_TIME_SYM real_ulong_num' => [AccountOptionKind::LockTime, 1],
    ];

    /**
     * The resource limit productions, by the limit they write.
     */
    private const LIMITS = [
        'connect_option: MAX_QUERIES_PER_HOUR ulong_num' => ResourceKind::QueriesPerHour, 'grant_option: MAX_QUERIES_PER_HOUR ulong_num' => ResourceKind::QueriesPerHour,
        'connect_option: MAX_UPDATES_PER_HOUR ulong_num' => ResourceKind::UpdatesPerHour, 'grant_option: MAX_UPDATES_PER_HOUR ulong_num' => ResourceKind::UpdatesPerHour,
        'connect_option: MAX_CONNECTIONS_PER_HOUR ulong_num' => ResourceKind::ConnectionsPerHour, 'grant_option: MAX_CONNECTIONS_PER_HOUR ulong_num' => ResourceKind::ConnectionsPerHour,
        'connect_option: MAX_USER_CONNECTIONS_SYM ulong_num' => ResourceKind::UserConnections, 'grant_option: MAX_USER_CONNECTIONS_SYM ulong_num' => ResourceKind::UserConnections,
    ];

    /**
     * The certificate condition productions, by the property they name.
     */
    private const CONDITIONS = [
        'require_list_element: SUBJECT_SYM TEXT_STRING' => TlsAttribute::Subject, 'require_list_element: ISSUER_SYM TEXT_STRING' => TlsAttribute::Issuer,
        'require_list_element: CIPHER_SYM TEXT_STRING' => TlsAttribute::Cipher,
    ];

    /**
     * The list productions this rule flattens.
     */
    private const LISTS = [
        'connect_option_list: connect_option_list connect_option' => true, 'connect_option_list: connect_option' => true,
        'grant_option_list: grant_option_list grant_option' => true, 'grant_option_list: grant_option' => true,
        'opt_account_lock_password_expire_option_list: opt_account_lock_password_expire_option' => true,
        'opt_account_lock_password_expire_option_list: opt_account_lock_password_expire_option_list opt_account_lock_password_expire_option' => true,
        'require_list: require_list_element opt_and require_list' => true, 'require_list: require_list_element' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a require_clause; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function tls(Node $clause): ?TlsRequirement
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'require_clause:' => null,
            'require_clause: REQUIRE_SYM NONE_SYM' => new TlsRequirement(TlsKind::None),
            'require_clause: REQUIRE_SYM SSL_SYM' => new TlsRequirement(TlsKind::Ssl),
            'require_clause: REQUIRE_SYM X509_SYM' => new TlsRequirement(TlsKind::X509),
            'require_clause: REQUIRE_SYM require_list' => new TlsRequirement(TlsKind::Specified, $this->conditions($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the conditions of a require_list, confirming the optional AND between them.
     *
     * @return list<TlsCondition>
     * @throws ImplementationGap When a production has no rule
     */
    public function conditions(Node $list): array
    {
        $conditions = [];
        foreach ($this->spine($list) as $item) {
            $form = $this->lowering->form($item);
            if ($form->signature === 'opt_and:' || $form->signature === 'opt_and: AND_SYM') {
                continue;
            }
            $attribute = self::CONDITIONS[$form->signature] ?? throw ImplementationGap::production($form);
            $conditions[] = new TlsCondition($attribute, $this->string($form, 1));
        }

        return $conditions;
    }

    /**
     * Lowers a connect_options clause of CREATE USER or ALTER USER; an absent clause is empty.
     *
     * @return list<ResourceLimit>
     * @throws ImplementationGap When a production has no rule
     */
    public function limits(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'connect_options:') {
            return [];
        }
        if ($form->signature !== 'connect_options: WITH connect_option_list') {
            throw ImplementationGap::production($form);
        }
        $limits = [];
        foreach ($this->spine($form->node(1)) as $item) {
            $limits[] = $this->limit($this->lowering->form($item));
        }

        return $limits;
    }

    /**
     * Lowers one resource limit.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function limit(Form $form): ResourceLimit
    {
        $kind = self::LIMITS[$form->signature] ?? throw ImplementationGap::production($form);

        return new ResourceLimit($kind, $this->lowering->numbers->numeral($form->node(1)));
    }

    /**
     * Lowers the grant_options of GRANT: WITH GRANT OPTION (8.0+) or a WITH list of GRANT OPTION and limits (5.x).
     *
     * @return list<WithOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function grantOptions(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'grant_options:') {
            return [];
        }
        if ($form->signature === 'grant_options: WITH GRANT OPTION') {
            return [new GrantOptionRight()];
        }
        if ($form->signature !== 'grant_options: WITH grant_option_list') {
            throw ImplementationGap::production($form);
        }
        $options = [];
        foreach ($this->spine($form->node(1)) as $item) {
            $option = $this->lowering->form($item);
            $options[] = $option->signature === 'grant_option: GRANT OPTION' ? new GrantOptionRight() : $this->limit($option);
        }

        return $options;
    }

    /**
     * Lowers an opt_grant_option: whether WITH GRANT OPTION is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function grantOption(Node $clause): bool
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_grant_option:' => false,
            'opt_grant_option: WITH GRANT OPTION' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the password and locking options; absent options are empty.
     *
     * @return list<AccountOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_account_lock_password_expire_options:') {
            return [];
        }
        if ($form->signature !== 'opt_account_lock_password_expire_options: opt_account_lock_password_expire_option_list') {
            throw ImplementationGap::production($form);
        }
        $options = [];
        foreach ($this->spine($form->node(0)) as $item) {
            $options[] = $this->option($item);
        }

        return $options;
    }

    /**
     * Lowers one password or locking option.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function option(Node $option): AccountOption
    {
        $form = $this->lowering->form($option);
        if (str_starts_with($form->signature, 'opt_account_lock_password_expire_option: password_expire')) {
            $this->expire($form->node(0));
        }
        $kind = self::OPTIONS[$form->signature] ?? null;
        if ($kind !== null) {
            return new AccountOption($kind);
        }
        [$kind, $position] = self::NUMBERED[$form->signature] ?? throw ImplementationGap::production($form);

        return new AccountOption($kind, $this->lowering->numbers->numeral($form->node($position)));
    }

    /**
     * Confirms the MySQL 5.7 password_expire production, whose marker holds no operand.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function expire(Node $expire): void
    {
        $form = $this->lowering->form($expire);
        if ($form->signature !== 'password_expire: PASSWORD EXPIRE_SYM clear_password_expire_options') {
            throw ImplementationGap::production($form);
        }
        $this->lowering->options->skip($form->node(2));
    }

    /**
     * Lowers an opt_user_attribute; an absent clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function comment(Node $clause): ?UserComment
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_user_attribute:' => null,
            'opt_user_attribute: ATTRIBUTE_SYM TEXT_STRING_literal' => new UserComment(CommentKind::Attribute, $this->lowering->literals->text($form->node(1))),
            'opt_user_attribute: COMMENT_SYM TEXT_STRING_literal' => new UserComment(CommentKind::Comment, $this->lowering->literals->text($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a quoted string token at a position that is not an expression.
     */
    public function string(Form $form, int $position): Text
    {
        $literals = $this->lowering->literals;

        return $this->lowering->leaves->record(new Text($literals->decode($form->token($position)->text), $literals->escapes()));
    }

    /**
     * Answers the items of a list production of this rule, in source order.
     *
     * @return list<Node>
     * @throws ImplementationGap When the production has no rule
     */
    public function spine(Node $list): array
    {
        $form = $this->lowering->form($list);
        if (!isset(self::LISTS[$form->signature])) {
            throw ImplementationGap::production($form);
        }

        return (new Lists())->items($list);
    }
}
