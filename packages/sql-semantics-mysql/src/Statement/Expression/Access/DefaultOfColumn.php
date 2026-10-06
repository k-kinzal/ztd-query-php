<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The default value of a column: `DEFAULT(col)` (`Item_default_value`).
 *
 * Rule: MYSQL-COLUMN-DEFAULT-001. Facts: the type and NULL fact of the
 * column; a NOT NULL column has no NULL default (the server rejects the
 * function for a NOT NULL column without default). Terminates: the column
 * is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html#function_default.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the column
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = DEFAULT(a)');
 *     $query->statement->where->right->column->name->value // => 'a'
 */
final class DefaultOfColumn implements Scalar
{
    use Snapshot;

    /**
     * @param ColumnUse $column The column whose default is read
     */
    public function __construct(public readonly ColumnUse $column)
    {
    }

    /**
     * Derives the column; the default has its facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->column, $environment);

        return new ScalarFact($fact->type, $fact->nullability);
    }

    /**
     * Writes DEFAULT and the column in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT')->glue()->symbol('(')->node($this->column)->symbol(')');
    }
}
