<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Access;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * The value an INSERT would have inserted into a column: `VALUES(col)` in `ON DUPLICATE KEY UPDATE` (`Item_insert_value`).
 *
 * The function is deprecated from MySQL 8.0.20 in favour of a row alias.
 *
 * Rule: MYSQL-INSERTED-COLUMN-001. Facts: the type of the column; the
 * value is NULL outside `ON DUPLICATE KEY UPDATE`, so it can always be
 * NULL. Terminates: the column is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html#function_values.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class InsertedColumn implements Scalar
{
    use Snapshot;

    /**
     * @param ColumnUse $column The column whose inserted value is read
     */
    public function __construct(public readonly ColumnUse $column)
    {
    }

    /**
     * Derives the column; the value has its type and can be NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact($derivation->scalar($this->column, $environment)->type, Nullability::Nullable);
    }

    /**
     * Writes VALUES and the column in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALUES')->glue()->symbol('(')->node($this->column)->symbol(')');
    }
}
