<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `BEGIN [WORK]`: a request to start a transaction without characteristics.
 *
 * Mirrors SQLCOM_BEGIN with no start options. Rule: MYSQL-BEGIN-001. BEGIN
 * is the alias of START TRANSACTION that takes no characteristics; inside a
 * stored program the word starts a compound statement instead, which is a
 * different production. WORK is optional and not written. The statement
 * names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/commit.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Starting a transaction with BEGIN WORK
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('BEGIN WORK')->toString() // => 'BEGIN'
 */
final class Begin implements Statement
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
        $out->keyword('BEGIN');
    }
}
