<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Replication;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Parse\Entry\Literal\LineText;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;

/**
 * Formats intrinsic replication operand refusals without a completed statement.
 *
 * @visibility MySqlMemory
 */
final class Settings
{
    /**
     * Resolves the release-specific diagnostic of a retained input operand.
     */
    public function error(RefusedSetting $problem, Session $session): ?SqlError
    {
        if ($problem->error === ReplicationError::LineFeed && $problem->text !== null) {
            return (new LineText())->error($problem->text, $session);
        }
        if ($problem->error === ReplicationError::UntilCondition) {
            return new SqlError(AdministrationError::BadReplicaUntil, $session->settings()->legacy() ? 'Incorrect parameter or combination of parameters for START SLAVE UNTIL' : 'Incorrect parameter or combination of parameters for START REPLICA UNTIL');
        }

        return null;
    }
}
