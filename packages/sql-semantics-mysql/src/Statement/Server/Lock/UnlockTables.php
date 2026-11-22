<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `UNLOCK {TABLE | TABLES}`: a request to release the table locks of the session.
 *
 * Mirrors SQLCOM_UNLOCK_TABLES. Rule: MYSQL-UNLOCK-TABLES-001. TABLE and TABLES are synonyms; TABLES is written. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class UnlockTables implements Statement
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
        $out->keyword('UNLOCK', 'TABLES');
    }
}
