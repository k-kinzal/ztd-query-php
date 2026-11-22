<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Platform\MySql\Statement\Name\Account;

/**
 * A password assignment among other assignments of a MySQL 5.6 SET: `SET @a = 1, PASSWORD FOR u = PASSWORD('x')`.
 *
 * Rule: MYSQL-SET-PASSWORD-001. MySQL 5.6 reads `PASSWORD [FOR user] = …`
 * as one item of the SET list; it assigns the password of the account, or
 * of the current user without FOR, in order with the other items. A SET
 * whose only item is a password is the account statement SET PASSWORD of
 * the account family. Facts: none; the password is a literal or a hashing
 * call the account family models. Terminates: the parts are leaves.
 * Source: https://dev.mysql.com/doc/refman/5.6/en/set-password.html,
 * https://dev.mysql.com/doc/refman/5.6/en/set-variable.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class PasswordAssignment implements SetItem
{
    use Snapshot;

    /**
     * @param Password $password The new password
     * @param Account|null $user The account, or null for the current user
     */
    public function __construct(public readonly Password $password, public readonly ?Account $user = null)
    {
    }

    /**
     * Derives nothing: the parts are literals and account names.
     */
    public function deriveItem(Derivation $derivation): void
    {
    }

    /**
     * Writes PASSWORD, the account, an equals sign and the password.
     */
    public function render(Output $out): void
    {
        $out->keyword('PASSWORD');
        if ($this->user !== null) {
            $out->keyword('FOR')->node($this->user);
        }
        $out->symbol('=')->node($this->password);
    }
}
