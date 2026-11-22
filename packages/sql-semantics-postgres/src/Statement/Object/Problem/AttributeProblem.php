<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\WarningDiagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A problem the defining command reports about the attributes of CREATE AGGREGATE, OPERATOR, TYPE, COLLATION or TEXT SEARCH.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createoperator.html, https://www.postgresql.org/docs/17/sql-createaggregate.html.
 *
 * @visibility SqlSemantics
 */
final class AttributeProblem implements WarningDiagnostic
{
    use Snapshot;

    /**
     * @var list<string> The names and texts the message mentions, in order
     */
    public readonly array $subjects;

    /**
     * @param AttributeProblemKind $kind What is wrong
     * @param list<string> $subjects The names and texts the message mentions, in order
     */
    public function __construct(public readonly AttributeProblemKind $kind, array $subjects = [])
    {
        Check::input(substr_count($kind->format(), '%s') === count($subjects), 'A problem has one subject for each place in its message.');
        $this->subjects = $subjects;
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return vsprintf($this->kind->format(), $this->subjects);
    }

    /**
     * Tells whether the server only warns and goes on.
     */
    public function warning(): bool
    {
        return $this->kind->warning();
    }
}
