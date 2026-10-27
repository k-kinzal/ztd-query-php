<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Contract\NameUse;
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
 * Construction excludes names outside every supported release's domain:
 * more than 32 trimmed user characters, more than 255 trimmed host bytes,
 * or an at sign in the host. Older release limits remain explicit inputs
 * to account interpretation.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-names.html,
 * https://dev.mysql.com/doc/refman/8.4/en/role-names.html.
 *
 * @visibility public
 * @example Reading the parts of an account name
 *     $account = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('app'), new \SqlSemantics\Statement\Identifier\Name('10.0.%'));
 *     [$account->user->value, $account->host?->value] // => ['app', '10.0.%']
 * @example Refusing a user name outside every supported release
 *     new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name(str_repeat('u', 33))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an illegal host separator
 *     new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('u'), new \SqlSemantics\Statement\Identifier\Name('a@b')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class AccountName implements Account
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $user The user part
     * @param Name|null $host The host part, when written
     */
    public function __construct(public readonly Name $user, public readonly ?Name $host = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        \SqlSemantics\Diagnostic\Check::input((new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->problem($user, $host) === null, 'An account name satisfies the supported user and host domains.');
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
        $out->name($this->user, NameUse::Label);
        if ($this->host !== null) {
            $out->glue()->symbol('@')->glue()->name($this->host, NameUse::Label);
        }
    }
}
