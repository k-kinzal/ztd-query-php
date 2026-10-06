<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `RESTART`: a request to stop and start the server under its supervisor (MySQL 8.0.12 and later).
 *
 * Mirrors PT_restart_server (Sql_cmd_restart_server). Rule: MYSQL-RESTART-001. The statement names no relation
 * and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/restart.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('restart')->toString() // => 'RESTART'
 */
final class Restart implements Statement
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
        $out->keyword('RESTART');
    }
}
