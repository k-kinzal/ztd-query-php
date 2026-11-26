<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;

/**
 * `RENAME USER old TO new, …`: a request to rename accounts, applied pair by pair in order.
 *
 * Mirrors SQLCOM_RENAME_USER. Rule: MYSQL-RENAME-USER-001. Accounts are not
 * declared in a context, so the statement has no resolution facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-user.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class RenameUser implements Statement
{
    use Snapshot;

    /**
     * @var list<UserRenaming> The renamings in order
     */
    public readonly array $renamings;

    /**
     * @param list<UserRenaming> $renamings The renamings in order; at least one
     */
    public function __construct(array $renamings)
    {
        $this->renamings = Check::listOf($renamings, UserRenaming::class, 'RENAME USER names at least one pair.', 1);
    }

    /**
     * Derives nothing: the statement names no relation and no expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('RENAME', 'USER')->list($this->renamings);
    }
}
