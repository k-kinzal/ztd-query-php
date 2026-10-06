<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A value other than DEFAULT that a statement writes into a generated column.
 *
 * The server names the column and the table as they are declared, not as
 * the statement spells them. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html,
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html (ER_NON_DEFAULT_VALUE_FOR_GENERATED_COLUMN).
 *
 * @visibility public
 * @example Reading the generated column written by an INSERT
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $table = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a + 1))');
 *     $semantics->analyze('INSERT INTO t (a, g) VALUES (1, 2)', $table->declarations())->facts->diagnostics[0]->message() // => "The value specified for generated column 'g' in table 't' is not allowed."
 */
final class GeneratedColumnWrite implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The declared name of the generated column
     * @param Name $table The declared name of its table
     */
    public function __construct(public readonly Name $column, public readonly Name $table)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return "The value specified for generated column '" . $this->column->value . "' in table '" . $this->table->value . "' is not allowed.";
    }
}
