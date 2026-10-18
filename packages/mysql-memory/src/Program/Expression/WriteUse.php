<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write as W;

/**
 * Prepares complete stored write templates when their instruction is reached.
 *
 * @visibility MySqlMemory
 */
final class WriteUse
{
    /**
     * Runs the template's preparation and preserves a conditional CREATE completion.
     */
    public function prepare(W\DeferredRevokePrivileges|W\DeferredAlterTable|W\DeferredDelete|W\DeferredMultipleDelete|W\DeferredInsertRows|W\DeferredInsertQuery|W\DeferredCreateTable $statement, Session $session): void
    {
        if ($statement instanceof W\DeferredRevokePrivileges) {
            (new \MySqlMemory\Program\Account\RevokeUse())->raise($statement, $session);
        } elseif ($statement instanceof W\DeferredAlterTable) {
            (new AlterUse())->raise($statement, $session);
        } elseif ($statement instanceof W\DeferredCreateTable) {
            (new TableUse())->raise($statement, $session);
        } elseif ($statement instanceof W\DeferredMultipleDelete || $statement instanceof W\DeferredDelete) {
            (new DeleteUse())->raise($statement, $session);
        } else {
            (new InsertUse())->raise($statement, $session);
        }
    }
}
