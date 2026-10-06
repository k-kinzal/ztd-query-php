<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A column name in a definition or alteration that the completely known table does not have.
 *
 * It is reported for the child columns of a foreign key and for the column
 * that ALTER TABLE drops or renames. A name inside an expression is reported
 * by the expression itself as a missing column.
 * Source: https://sqlite.org/lang_altertable.html, https://sqlite.org/foreignkeys.html.
 *
 * @visibility public
 * @example Reporting a dropped column that does not exist
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a, b)');
 *     $semantics->analyze('ALTER TABLE t DROP COLUMN c', [$table])->facts->diagnostics[0]->message() // => 'Column c does not exist in the table.'
 */
final class UnknownColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The name that denotes no column
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column ' . $this->column->value . ' does not exist in the table.';
    }
}
