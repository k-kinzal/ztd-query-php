<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Access;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

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
 * @visibility SqlSemantics
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
