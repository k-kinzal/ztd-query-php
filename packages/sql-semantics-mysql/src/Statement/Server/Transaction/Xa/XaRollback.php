<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `XA ROLLBACK xid`: a request to roll back an XA transaction.
 *
 * Mirrors Sql_cmd_xa_rollback. Rule: MYSQL-XA-ROLLBACK-001. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $xa = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("xa rollback X'0f', 'b', 3");
 *     [$xa->toString(), $xa->statement->xid->format?->text === '3'] // => ["XA ROLLBACK x'0f', 'b', 3", true]
 */
final class XaRollback implements Statement
{
    use Snapshot;

    /**
     * @param Xid $xid The transaction identifier
     */
    public function __construct(public readonly Xid $xid)
    {
    }

    /**
     * Refuses a format identifier the release rejects; the request names no relation and no value.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the release does not read the format identifier
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Magnitudes())->identifier($this->xid, $derivation->context->profile->grammar);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('XA', 'ROLLBACK')->node($this->xid);
    }
}
