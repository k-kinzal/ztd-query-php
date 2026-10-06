<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\With;

/**
 * Whether a common table expression is computed once or folded into the query that uses it.
 *
 * Mirrors PostgreSQL's `CTEMaterialize` without DEFAULT, which is the
 * absence of the clause and is kept as null.
 * Source: https://www.postgresql.org/docs/17/queries-with.html#QUERIES-WITH-CTE-MATERIALIZATION.
 *
 * @visibility public
 * @example Spelling the choice that folds the query in
 *     \SqlSemantics\Platform\PostgreSql\Statement\Query\With\Materialization::NotMaterialized->value // => 'NOT MATERIALIZED'
 */
enum Materialization: string
{
    case Materialized = 'MATERIALIZED';
    case NotMaterialized = 'NOT MATERIALIZED';
}
