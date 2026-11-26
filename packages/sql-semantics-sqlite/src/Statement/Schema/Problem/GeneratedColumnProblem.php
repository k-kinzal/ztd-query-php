<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A table definition that uses a generated column in a way SQLite rejects.
 *
 * "Generated columns may not be used as part of the PRIMARY KEY", "generated
 * columns may not have a default value", and "every table must have at
 * least one non-generated column".
 * Source: https://sqlite.org/gencol.html#limitations.
 *
 * @visibility SqlSemantics
 */
final class GeneratedColumnProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param GeneratedColumnFlaw $flaw What is wrong
     * @param Name|null $column The generated column, or null when the problem is the table as a whole
     */
    public function __construct(public readonly GeneratedColumnFlaw $flaw, public readonly ?Name $column = null)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->flaw->value;
    }
}
