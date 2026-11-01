<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Notation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * A user or role account name: the user part and the optional host part.
 *
 * An account written without a host part denotes the host `%`; the host is
 * kept absent, as written. Role names have the same two parts. The decoded
 * operands retain their spelling for rendering; username() and hostname()
 * remove surrounding ASCII whitespace for account resolution. This behavior
 * was verified on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 * Construction preserves operands outside the selected release's name domain.
 * The owning request derives the user-character and host-byte conditions without
 * discarding the account, its notation or the following clauses.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-names.html,
 * https://dev.mysql.com/doc/refman/8.4/en/role-names.html.
 *
 * @visibility public
 * @example Reading the parts of an account name
 *     $account = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('app'), new \SqlSemantics\Statement\Identifier\Name('10.0.%'));
 *     [$account->user->value, $account->host?->value] // => ['app', '10.0.%']
 * @example Retaining an operand outside the account domain
 *     $account = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name(str_repeat('u', 33)), new \SqlSemantics\Statement\Identifier\Name('a@b'));
 *     [$account->username(), $account->hostname()] // => [str_repeat('u', 33), 'a@b']
 */
final class AccountName implements Account
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $user The user part
     * @param Name|null $host The host part, when written
     * @param Notation|null $userQuotation The string delimiter and escape choices of the user
     * @param Notation|null $hostQuotation The string delimiter and escape choices of the host
     */
    public function __construct(public readonly Name $user, public readonly ?Name $host = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?Notation $userQuotation = null, public readonly ?Notation $hostQuotation = null)
    {
        \SqlSemantics\Diagnostic\Check::input($userQuotation === null || in_array($user->quote, [null, \SqlSemantics\Statement\Identifier\NameQuote::SingleQuote], true), 'A string account operand has no identifier delimiter.');
        \SqlSemantics\Diagnostic\Check::input($hostQuotation === null || ($host !== null && in_array($host->quote, [null, \SqlSemantics\Statement\Identifier\NameQuote::SingleQuote], true)), 'A string host operand is present and has no identifier delimiter.');
        $userQuotation?->validate($user->value);
        $hostQuotation?->validate($host->value ?? '');
    }

    /**
     * Answers the user operand without surrounding ASCII whitespace.
     *
     * NUL, other control bytes and Unicode spaces remain part of the operand.
     */
    public function username(): string
    {
        return trim($this->user->value, " \t\n\r\v\f");
    }

    /**
     * Answers the host operand without surrounding ASCII whitespace, or null when omitted.
     *
     * Host case is preserved here, including for diagnostics before account lookup.
     */
    public function hostname(): ?string
    {
        return $this->host === null ? null : trim($this->host->value, " \t\n\r\v\f");
    }

    /**
     * Answers the reading notices owned by the written user and host operands.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation>
     */
    public function warnings(\SqlSemantics\Contract\GrammarRelease $release): array
    {
        $warnings = [];
        foreach ([$this->user, $this->host] as $name) {
            $warning = $name === null ? null : \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::unqualifiedName($name, $release);
            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }
        return $warnings;
    }

    /**
     * Writes the user part and, joined by `@` without spaces, the host part.
     */
    public function render(Output $out): void
    {
        if ($this->userQuotation !== null) {
            $out->spelledName($this->userQuotation->spell($this->user->value));
        } else {
            $out->name($this->user, NameUse::Label);
        }
        if ($this->host !== null) {
            $out->glue()->symbol('@')->glue();
            if ($this->hostQuotation !== null) {
                $out->spelledName($this->hostQuotation->spell($this->host->value));
            } else {
                $out->name($this->host, NameUse::Label);
            }
        }
    }
}
