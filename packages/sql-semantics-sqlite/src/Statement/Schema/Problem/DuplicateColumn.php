<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A column name a table would have twice.
 *
 * SQLite compares column names without regard to ASCII case and rejects the
 * definition, the added column or the new column name with "duplicate column name".
 * Source: https://sqlite.org/lang_createtable.html, https://sqlite.org/lang_altertable.html.
 *
 * @visibility public
 * @example Reporting a repeated column name
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a, A)');
 *     $create->facts->diagnostics[0]->message() // => 'Column A is defined more than once.'
 */
final class DuplicateColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The repeated name as written at its later occurrence
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column ' . $this->column->value . ' is defined more than once.';
    }
}
