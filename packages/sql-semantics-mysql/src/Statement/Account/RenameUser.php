<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `RENAME USER old TO new, …`: a request to rename accounts, applied pair by pair in order.
 *
 * Mirrors SQLCOM_RENAME_USER. Rule: MYSQL-RENAME-USER-001. Accounts are not
 * declared in a context, so the statement has no resolution facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-user.html. Status: Implemented.
 *
 * @visibility public
 * @example Renaming two accounts
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('rename user a to b, c to d')->toString() // => 'RENAME USER a TO b, c TO d'
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
