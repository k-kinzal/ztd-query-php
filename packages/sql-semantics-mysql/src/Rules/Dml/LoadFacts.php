<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;

/**
 * Derives the facts of LOAD DATA and LOAD XML.
 *
 * Rule: MYSQL-LOAD-001. The table is resolved like the table of INSERT
 * (MYSQL-DML-TARGET-001) and is the one visible relation of the column
 * list and of the SET assignments; a user variable of the column list is a
 * session value. A SET value may refer to columns and user variables; the
 * value DEFAULT is the default of its column. The file is read by the
 * server or the client and is not examined. The statement returns no rows.
 * Terminates: one pass over the finite lists. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/load-data.html,
 * https://dev.mysql.com/doc/refman/8.4/en/load-xml.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LoadFacts
{
    /**
     * Derives the table, the columns and the assignments.
     */
    public function derive(LoadTable $load, Derivation $derivation): void
    {
        $base = $derivation->environment();
        $fact = $derivation->relation($load->table, $base);
        $environment = new Environment($derivation->context, $base, [new VisibleRelation($load->table, $fact->shape, null, $load->table->name)]);
        foreach ($load->columns as $column) {
            $derivation->scalar($column, $column instanceof ColumnUse ? $environment : $base);
        }
        (new WriteScope())->assign($load->assignments, $derivation, $environment, $environment, false);
    }
}
