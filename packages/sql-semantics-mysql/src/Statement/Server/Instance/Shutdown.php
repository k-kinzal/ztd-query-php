<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `SHUTDOWN`: a request to stop the server (MySQL 5.7.9 and later).
 *
 * Mirrors PT_shutdown (Sql_cmd_shutdown). Rule: MYSQL-SHUTDOWN-001. The statement names no relation
 * and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/shutdown.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('shutdown')->toString() // => 'SHUTDOWN'
 */
final class Shutdown implements Statement
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
        $out->keyword('SHUTDOWN');
    }
}
