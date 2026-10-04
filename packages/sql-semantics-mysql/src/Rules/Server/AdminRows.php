<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowRows;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Shape\Field;

/**
 * Records the rows the row-returning server administration statements return.
 *
 * Rule: MYSQL-ADMIN-ROWS-001. The table maintenance and key cache
 * statements (ANALYZE, CHECK, OPTIMIZE, REPAIR TABLE, CACHE INDEX, LOAD
 * INDEX INTO CACHE) return the result of mysql_admin_table: Table, Op,
 * Msg_type and Msg_text, each a VARCHAR that can be NULL. CHECKSUM TABLE
 * returns Table, a VARCHAR, and Checksum, a BIGINT, both of which can be
 * NULL (Checksum is NULL for a table that does not exist). XA RECOVER
 * returns formatID, gtrid_length and bqual_length, BIGINT, and data, a
 * VARCHAR, none of which is NULL. The types are those of the result
 * metadata the server declares (sql_admin.cc, sql_table.cc, xa.cc); the
 * column codes are decoded by MYSQL-SHOW-ROWS-001. Precision: every column
 * is a known type. Terminates: one pass over the columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/check-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/checksum-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AdminRows
{
    /**
     * The columns of each result, as names and column codes.
     */
    private const COLUMNS = [
        'admin' => [['Table', 'v?'], ['Op', 'v?'], ['Msg_type', 'v?'], ['Msg_text', 'v?']],
        'checksum' => [['Table', 'v?'], ['Checksum', 'b?']],
        'recover' => [['formatID', 'b'], ['gtrid_length', 'b'], ['bqual_length', 'b'], ['data', 'v']],
    ];

    /**
     * Records the result of a table maintenance or key cache statement.
     */
    public function admin(Derivation $derivation): void
    {
        $this->record($derivation, 'admin');
    }

    /**
     * Records the result of CHECKSUM TABLE.
     */
    public function checksum(Derivation $derivation): void
    {
        $this->record($derivation, 'checksum');
    }

    /**
     * Records the result of XA RECOVER.
     */
    public function recover(Derivation $derivation): void
    {
        $this->record($derivation, 'recover');
    }

    /**
     * Records the rows of a result layout.
     *
     * @param 'admin'|'checksum'|'recover' $layout
     */
    public function record(Derivation $derivation, string $layout): void
    {
        $fields = [];
        foreach ((new ShowRows())->slots(self::COLUMNS[$layout]) as $position => $slot) {
            $fields[] = new Field($position, $slot);
        }
        $derivation->output(new QueryFact($fields, $derivation->context->columnNames));
    }
}
