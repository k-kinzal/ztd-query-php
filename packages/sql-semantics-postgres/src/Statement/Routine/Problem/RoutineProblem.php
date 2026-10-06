<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A routine, aggregate or operator definition, signature or option list that the server rejects.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Reading the message of a repeated parameter name
 *     $problem = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem(\SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind::DuplicateParameter, 'a');
 *     $problem->message() // => 'parameter name "a" used more than once'
 */
final class RoutineProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param RoutineProblemKind $kind What is wrong
     * @param string $subject The name the message mentions, if any
     */
    public function __construct(public readonly RoutineProblemKind $kind, public readonly string $subject = '')
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
