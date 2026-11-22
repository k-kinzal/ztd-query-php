<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\Account;

/**
 * One `old_user TO new_user` pair of RENAME USER.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-user.html.
 *
 * @visibility SqlSemantics
 * @example Holding a renaming
 *     $pair = new \SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('b')));
 *     $pair->to instanceof \SqlSemantics\Platform\MySql\Statement\Name\AccountName // => true
 */
final class UserRenaming implements Node
{
    use Snapshot;

    /**
     * @param Account $from The account renamed
     * @param Account $to Its new name
     */
    public function __construct(public readonly Account $from, public readonly Account $to)
    {
    }

    /**
     * Writes the pair.
     */
    public function render(Output $out): void
    {
        $out->node($this->from)->keyword('TO')->node($this->to);
    }
}
