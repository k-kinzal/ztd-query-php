<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `XA {START | BEGIN} xid [JOIN | RESUME]`: a request to start an XA transaction.
 *
 * Mirrors Sql_cmd_xa_start. Rule: MYSQL-XA-START-001. BEGIN is a synonym of START and START is written. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $xa = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("xa begin 'g', 'b' resume");
 *     [$xa->toString(), $xa->statement->option === \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStartOption::Resume] // => ["XA START 'g', 'b' RESUME", true]
 */
final class XaStart implements Statement
{
    use Snapshot;

    /**
     * @param Xid $xid The transaction identifier
     * @param XaStartOption|null $option JOIN or RESUME, when written
     */
    public function __construct(public readonly Xid $xid, public readonly ?XaStartOption $option = null)
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
        $out->keyword('XA', 'START')->node($this->xid);
        if ($this->option !== null) {
            $out->keyword($this->option->value);
        }
    }
}
