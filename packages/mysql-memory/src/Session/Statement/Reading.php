<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Statement;

use MySqlMemory\Command\Account\RenameUserCommand;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement;
use SqlSemantics\Statement\AnalyzedStatement;

/**
 * Applies initial operand conditions of concrete statements before access and execution.
 *
 * Preparation and ordinary execution use the same conditions. Replayed and
 * stored statements retain domain checks but do not repeat reading notices.
 * This phase neither reads an SQL buffer nor changes the account or table catalog.
 *
 * @visibility MySqlMemory
 */
final class Reading
{
    /**
     * @param bool $notices Whether this is the initial reading of the operands
     */
    public function read(AnalyzedStatement $statement, Session $session, bool $notices = true): void
    {
        if ($statement instanceof TransactionStatement && $notices) {
            (new TransactionNotices())->read($statement, $session);
        }
        if ($statement instanceof RenameUser) {
            (new RenameUserCommand())->prepare($statement, $session, $notices);
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike) {
            (new \MySqlMemory\Command\Definition\CreateTableLikeCommand())->prepare($statement, $session);
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateRole) {
            (new \MySqlMemory\Command\Account\Role\CreateRoleCommand())->prepare($statement, $session, $notices);
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles) {
            (new \MySqlMemory\Command\Account\Role\GrantRolesCommand())->prepare($statement, $session, $notices);
        }
    }
}
