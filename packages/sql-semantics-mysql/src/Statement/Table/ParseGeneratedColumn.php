<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * The internal statement PARSE_GCOL_EXPR(expr) of MySQL 5.7, which the server uses to read a stored generated column expression.
 *
 * Rule: MYSQL-PARSE-GCOL-EXPR-001. The grammar of 5.7 reaches it from SQL
 * text because PARSE_GCOL_EXPR is a keyword of its lexer; the server only
 * accepts it while it opens a table definition. Outside one, no table is
 * visible: the expression is derived at a position that sees no relation, so
 * a column name in it is a missing column.
 * Source: sql/sql_yacc.yy and sql/table.cc (`unpack_gcol_info`) of MySQL 5.7,
 * https://dev.mysql.com/doc/refman/5.7/en/create-table-generated-columns.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the expression
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('PARSE_GCOL_EXPR(1 + 2)');
 *     $statement->statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\ParseGeneratedColumn // => true
 */
final class ParseGeneratedColumn implements Statement
{
    use Snapshot;

    /**
     * @param Scalar $expression The generated column expression
     */
    public function __construct(public readonly Scalar $expression)
    {
    }

    /**
     * Derives the expression at a position that sees no relation.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->scalar($this->expression, $derivation->environment());
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('PARSE_GCOL_EXPR')->glue()->symbol('(')->node($this->expression)->symbol(')');
    }
}
