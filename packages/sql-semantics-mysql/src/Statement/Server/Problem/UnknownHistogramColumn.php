<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A column of a histogram request that the declared table does not have.
 *
 * The server reports that the column does not exist and builds or drops no
 * histogram for it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn(new \SqlSemantics\Statement\Identifier\Name('a')))->message() // => "The column 'a' does not exist."
 */
final class UnknownHistogramColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The column name as written
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return "The column '" . $this->column->value . "' does not exist.";
    }
}
