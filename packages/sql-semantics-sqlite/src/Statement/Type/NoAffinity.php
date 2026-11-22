<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\TypeDescriptor;

/**
 * The type of a view column whose expression has no affinity: no declared type, and values that pass through unchanged.
 *
 * Rule: SQLITE-VIEW-COLUMN-NO-AFFINITY-001. A table column without a
 * declared type has BLOB affinity, but a view column computed by an
 * expression without affinity (a literal, an operator, a function call) has
 * no affinity at all: SQLite records no type for it, and a further view that
 * reads the column records no type either, where a BLOB column would give
 * `BLOB`. The descriptor keeps that difference inside a declaration.
 * Source: https://sqlite.org/datatype3.html#affinity_of_expressions
 * (and `sqlite3SubqueryColumnTypes()` in select.c of the release). Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class NoAffinity implements TypeDescriptor
{
    use Snapshot;

    /**
     * Names the type as SQLite reports it: no declared type.
     */
    public function name(): string
    {
        return '';
    }
}
