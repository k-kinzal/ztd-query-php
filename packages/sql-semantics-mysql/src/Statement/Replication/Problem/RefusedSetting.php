<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A value of a replication statement that the server refuses while it parses the statement.
 *
 * The statement is grammatical and fully structured; the server rejects it
 * with the error the check names.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility public
 * @example Reading the refused value of a statement
 *     $change = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CHANGE REPLICATION SOURCE TO GTID_ONLY = 2');
 *     $change->facts->diagnostics[0]->message() // => 'SOURCE_CONNECTION_AUTO_FAILOVER and GTID_ONLY accept only 0 or 1 (ER_PARSE_ERROR).'
 */
final class RefusedSetting implements Diagnostic
{
    use Snapshot;

    /**
     * @param ReplicationError $error The check the value fails
     */
    public function __construct(public readonly ReplicationError $error)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->error->value;
    }
}
