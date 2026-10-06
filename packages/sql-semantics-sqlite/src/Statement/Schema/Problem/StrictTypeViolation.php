<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A column of a STRICT table whose declared type is missing or not one of the six allowed names.
 *
 * "Every column definition must specify a datatype for that column" and the
 * datatype must be INT, INTEGER, REAL, TEXT, BLOB or ANY.
 * Source: https://sqlite.org/stricttables.html.
 *
 * @visibility public
 * @example Reporting a type a STRICT table does not allow
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a VARCHAR(10)) STRICT');
 *     $create->facts->diagnostics[0]->message() // => 'Column a of a STRICT table has the unknown datatype VARCHAR(10).'
 */
final class StrictTypeViolation implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The column
     * @param string $declared The declared type as SQLite records it; empty when the column declares none
     */
    public function __construct(public readonly Name $column, public readonly string $declared)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->declared === ''
            ? 'Column ' . $this->column->value . ' of a STRICT table has no datatype.'
            : 'Column ' . $this->column->value . ' of a STRICT table has the unknown datatype ' . $this->declared . '.';
    }
}
