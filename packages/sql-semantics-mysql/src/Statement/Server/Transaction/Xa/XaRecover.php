<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `XA RECOVER [CONVERT XID]`: a request to list the prepared XA transactions.
 *
 * Mirrors Sql_cmd_xa_recover. Rule: MYSQL-XA-RECOVER-001. Its rows are those of MYSQL-ADMIN-ROWS-001. The statement names no
 * relation.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $xa = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("xa recover convert xid");
 *     [$xa->toString(), $xa->statement->convertXid] // => ["XA RECOVER CONVERT XID", true]
 */
final class XaRecover implements Statement
{
    use Snapshot;

    /**
     * @param bool $convertXid Whether CONVERT XID is written: the identifiers are listed in hexadecimal (MySQL 5.7 and later)
     */
    public function __construct(public readonly bool $convertXid = false)
    {
    }

    /**
     * Records the rows that list the prepared transactions.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new AdminRows())->recover($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('XA', 'RECOVER');
        if ($this->convertXid) {
            $out->keyword('CONVERT', 'XID');
        }
    }
}
