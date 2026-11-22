<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

/**
 * The binary boolean operators, as PostgreSQL's `BoolExprType` distinguishes them.
 *
 * Source: https://www.postgresql.org/docs/17/functions-logical.html.
 *
 * @visibility SqlSemantics
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
