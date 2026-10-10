<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The value an INSERT would have inserted into a column: `VALUES(col)` in `ON DUPLICATE KEY UPDATE` (`Item_insert_value`).
 *
 * The function is deprecated from MySQL 8.0.20 in favour of a row alias.
 *
 * Rule: MYSQL-INSERTED-COLUMN-001. Facts: in `ON DUPLICATE KEY UPDATE`
 * the column is a column of the table the INSERT writes, even where the row
 * alias or the source query has a column of the same name, and the value
 * has its type; elsewhere the value is NULL and of type NULL, and MySQL 8.0
 * and later deprecate the function without naming an alternative. The value
 * can always be NULL. The deprecation is raised once the column resolves,
 * so a column that does not resolve raises only its error (verified on live
 * 5.7, 8.0, 8.4 and 9.1 servers). Terminates: the column is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html#function_values.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the column
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE VALUES(a)');
 *     $query->facts->scalar($query->statement->where)->nullability // => \SqlSemantics\Statement\Type\Nullability::Nullable
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
        $written = array_values(array_filter($environment->relations, static fn (VisibleRelation $relation): bool => $relation->relation instanceof WriteTarget));
        $fact = $derivation->scalar($this->column, $written === [] ? $environment : new Environment($environment->context, $environment->outer, [$written[0]], $environment->commonTables));
        if (!$fact->type instanceof Invalid) {
            Deprecation::raise($written === [] ? Deprecated::ValuesElsewhere : Deprecated::ValuesFunction, $derivation);
        }

        return new ScalarFact($written === [] && !$fact->type instanceof Invalid ? new Known(Domain::null()) : $fact->type, Nullability::Nullable);
    }

    /**
     * Writes VALUES and the column in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALUES')->glue()->symbol('(')->node($this->column)->symbol(')');
    }
}
