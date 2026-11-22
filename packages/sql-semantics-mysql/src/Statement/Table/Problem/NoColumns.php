<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A CREATE TABLE without columns and without a query (ER_TABLE_MUST_HAVE_COLUMNS).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility SqlSemantics
 */
final class NoColumns implements Diagnostic
{
    use Snapshot;


    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return 'A table must have at least 1 column.';
    }
}
