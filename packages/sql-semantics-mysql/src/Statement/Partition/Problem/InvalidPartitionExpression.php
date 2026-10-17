<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A session-dependent, nondeterministic or stored expression forbidden while partitioning is read.
 *
 * The expression identifies the whole partition function or bound, not only the offending call.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-limitations.html.
 *
 * @visibility public
 * @example Identifying the forbidden partition function
 *     $expression = new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User, []);
 *     $problem = new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression($expression);
 *     $problem->expression === $expression // => true
 */
final class InvalidPartitionExpression implements Diagnostic
{
    use Snapshot;

    /**
     * @param Scalar $expression The forbidden partition expression
     */
    public function __construct(public readonly Scalar $expression)
    {
    }

    /**
     * Describes the early partition-expression restriction.
     */
    public function message(): string
    {
        return 'Constant, random or timezone-dependent expressions in (sub)partitioning function are not allowed';
    }
}
