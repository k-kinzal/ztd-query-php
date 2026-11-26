<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A table a multiple-table DELETE deletes from that none of its table references names.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/delete.html,
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html (ER_UNKNOWN_TABLE).
 *
 * @visibility SqlSemantics
 */
final class UnknownDeleteTable implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table name as written
     */
    public function __construct(public readonly QualifiedName $table)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return "Unknown table '" . $this->table->name->value . "' in MULTI DELETE";
    }
}
