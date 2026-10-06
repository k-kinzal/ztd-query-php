<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `XA END xid [SUSPEND [FOR MIGRATE]]`: a request to end the work of an XA transaction branch.
 *
 * Mirrors Sql_cmd_xa_end. Rule: MYSQL-XA-END-001. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $xa = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("xa end 'g' suspend for migrate");
 *     [$xa->toString(), $xa->statement->option === \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEndOption::SuspendForMigrate] // => ["XA END 'g' SUSPEND FOR MIGRATE", true]
 */
final class XaEnd implements Statement
{
    use Snapshot;

    /**
     * @param Xid $xid The transaction identifier
     * @param XaEndOption|null $option SUSPEND or SUSPEND FOR MIGRATE, when written
     */
    public function __construct(public readonly Xid $xid, public readonly ?XaEndOption $option = null)
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
        $out->keyword('XA', 'END')->node($this->xid);
        if ($this->option !== null) {
            $out->keyword(...explode(' ', $this->option->value));
        }
    }
}
