<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A value of a replication statement that the server refuses while it parses the statement.
 *
 * The statement is grammatical and fully structured; the server rejects it
 * with the error the check names.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility SqlSemantics
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
