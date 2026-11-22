<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `UNLOCK INSTANCE`: a request to release the backup lock of the session (MySQL 8.0 and later).
 *
 * Mirrors Sql_cmd_unlock_instance. Rule: MYSQL-UNLOCK-INSTANCE-001. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-instance-for-backup.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class UnlockInstance implements Statement
{
    use Snapshot;

    /**
     * Has nothing to derive: the request names no relation and no value.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('UNLOCK', 'INSTANCE');
    }
}
