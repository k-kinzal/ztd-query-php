<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * The source text of a result expression, which SQLite uses as the column name and the model does not keep.
 *
 * Rule: SQLITE-RESULT-NAME-001. A result column without an alias that is no
 * plain column reference is named after the text of its expression as it was
 * written. The model keeps the meaning of the expression and not its text,
 * so the name of such a column is not fixed, and a lookup that could only
 * match such a column depends on this input.
 * Source: https://sqlite.org/c3ref/column_name.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading why a column of a derived table cannot be decided by name
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT x FROM (SELECT 1 + 1)', []);
 *     $query->facts->scalar($query->statement->columns[0]->expression)->resolution->missing[0]->describe() // => 'the source text SQLite names an unaliased result expression after'
 */
final class UnkeptSpelling implements MissingInput
{
    use Snapshot;

    /**
     * Describes the missing input.
     */
    public function describe(): string
    {
        return 'the source text SQLite names an unaliased result expression after';
    }
}
