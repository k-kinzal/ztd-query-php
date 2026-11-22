<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Limit;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A DEFAULT expression that SQLite does not accept as a constant.
 *
 * "An expression is considered constant if it contains no sub-queries,
 * column or table references, bound parameters, or string literals enclosed
 * in double-quotes"; a RAISE function is not constant either. SQLite rejects
 * the definition with "default value of column [name] is not constant".
 * Source: https://sqlite.org/lang_createtable.html#the_default_clause.
 *
 * @visibility SqlSemantics
 */
final class DefaultNotConstant implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The column whose default is not constant
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'The default value of column ' . $this->column->value . ' is not constant.';
    }
}
