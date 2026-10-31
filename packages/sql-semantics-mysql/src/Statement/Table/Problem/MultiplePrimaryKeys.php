<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A table definition with more than one primary key (ER_MULTIPLE_PRI_KEY).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Reading the problem
 *     (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems())->problem((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT PRIMARY KEY, b INT PRIMARY KEY)')->body->elements, null, \SqlSemantics\Contract\GrammarRelease::MySql847)->message() // => 'Multiple primary key defined.'
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
