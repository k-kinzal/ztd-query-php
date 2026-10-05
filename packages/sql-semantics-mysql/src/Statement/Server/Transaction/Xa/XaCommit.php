<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `XA COMMIT xid [ONE PHASE]`: a request to commit an XA transaction.
 *
 * Mirrors Sql_cmd_xa_commit. Rule: MYSQL-XA-COMMIT-001. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $xa = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("xa commit 'g' one phase");
 *     [$xa->toString(), $xa->statement->onePhase] // => ["XA COMMIT 'g' ONE PHASE", true]
 */
final class XaCommit implements Statement
{
    use Snapshot;

    /**
     * @param Xid $xid The transaction identifier
     * @param bool $onePhase Whether ONE PHASE is written: prepare and commit in one step
     */
    public function __construct(public readonly Xid $xid, public readonly bool $onePhase = false)
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
        $out->keyword('XA', 'COMMIT')->node($this->xid);
        if ($this->onePhase) {
            $out->keyword('ONE', 'PHASE');
        }
    }
}
