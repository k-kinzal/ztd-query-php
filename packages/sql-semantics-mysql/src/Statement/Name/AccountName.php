<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A user or role account name: the user part and the optional host part.
 *
 * An account written without a host part denotes the host `%`; the host is
 * kept absent, as written. Role names have the same two parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/account-names.html,
 * https://dev.mysql.com/doc/refman/8.4/en/role-names.html.
 *
 * @visibility public
 * @example Reading the parts of an account name
 *     $account = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('app'), new \SqlSemantics\Statement\Identifier\Name('10.0.%'));
 *     [$account->user->value, $account->host?->value] // => ['app', '10.0.%']
 */
final class AccountName implements Account
{
    use Snapshot;

    /**
     * @param Name $user The user part
     * @param Name|null $host The host part, when written
     */
    public function __construct(public readonly Name $user, public readonly ?Name $host = null)
    {
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
