<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `UNLOCK {TABLE | TABLES}`: a request to release the table locks of the session.
 *
 * Mirrors SQLCOM_UNLOCK_TABLES. Rule: MYSQL-UNLOCK-TABLES-001. TABLE and TABLES are synonyms; TABLES is written. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('unlock table')->toString() // => 'UNLOCK TABLES'
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
