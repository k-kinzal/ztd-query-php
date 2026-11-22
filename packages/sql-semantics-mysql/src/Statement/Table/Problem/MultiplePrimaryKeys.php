<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A table definition with more than one primary key (ER_MULTIPLE_PRI_KEY).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility SqlSemantics
 */
final class MultiplePrimaryKeys implements Diagnostic
{
    use Snapshot;


    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return 'Multiple primary key defined.';
    }
}
