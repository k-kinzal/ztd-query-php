<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A table definition that uses a generated column in a way SQLite rejects.
 *
 * "Generated columns may not be used as part of the PRIMARY KEY", "generated
 * columns may not have a default value", and "every table must have at
 * least one non-generated column".
 * Source: https://sqlite.org/gencol.html#limitations.
 *
 * @visibility public
 * @example Reporting a table made of generated columns only
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a AS (1))');
 *     [$create->facts->diagnostics[0]->message(), $create->facts->diagnostics[0]->column] // => ['A table must have at least one column that is not generated.', null]
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
