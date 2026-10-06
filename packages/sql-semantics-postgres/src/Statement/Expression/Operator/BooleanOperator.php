<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

/**
 * The binary boolean operators, as PostgreSQL's `BoolExprType` distinguishes them.
 *
 * Source: https://www.postgresql.org/docs/17/functions-logical.html.
 *
 * @visibility public
 * @example Reading the operator of a conjunction
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT true AND false');
 *     $query->field(0)->expression->operator // => \SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperator::And
 */
enum BooleanOperator
{
    /**
     * `AND`.
     */
    case And;

    /**
     * `OR`.
     */
    case Or;
}
