<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * `USER()` at the account position of ALTER USER: the account of the session.
 *
 * ALTER USER USER() changes the password or the registration of the
 * session's own account; it is written only in the forms that a user may
 * apply to itself.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html.
 *
 * @visibility public
 * @example Rendering the session account
 *     $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::MySql847));
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser())->render($out);
 *     $out->pieces()[0]->text // => 'USER'
 */
final class SessionUser implements Node
{
    use Snapshot;

    /**
     * Writes `USER()`.
     */
    public function render(Output $out): void
    {
        $out->keyword('USER')->glue()->symbol('(')->glue()->symbol(')');
    }
}
