<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `LOCK INSTANCE FOR BACKUP`: a request to take the backup lock of the server instance (MySQL 8.0 and later).
 *
 * Mirrors Sql_cmd_lock_instance. Rule: MYSQL-LOCK-INSTANCE-001. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-instance-for-backup.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class LockInstance implements Statement
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
        $out->keyword('LOCK', 'INSTANCE', 'FOR', 'BACKUP');
    }
}
