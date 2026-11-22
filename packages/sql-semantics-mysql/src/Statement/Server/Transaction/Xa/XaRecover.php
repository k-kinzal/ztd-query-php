<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;

/**
 * `XA RECOVER [CONVERT XID]`: a request to list the prepared XA transactions.
 *
 * Mirrors Sql_cmd_xa_recover. Rule: MYSQL-XA-RECOVER-001. Its rows are those of MYSQL-ADMIN-ROWS-001. The statement names no
 * relation.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
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
