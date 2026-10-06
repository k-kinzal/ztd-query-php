<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A generic object command that the server rejects, such as a COMMENT ON COLUMN with an unqualified column.
 *
 * Source: https://www.postgresql.org/docs/17/sql-comment.html.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblem(\SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind::ConcurrentCascade))->message() // => 'DROP INDEX CONCURRENTLY does not support CASCADE'
 */
final class ObjectProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param ObjectProblemKind $kind What is wrong
     * @param string $subject The name the message mentions, if any
     */
    public function __construct(public readonly ObjectProblemKind $kind, public readonly string $subject = '')
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return sprintf($this->kind->value, $this->subject);
    }
}
