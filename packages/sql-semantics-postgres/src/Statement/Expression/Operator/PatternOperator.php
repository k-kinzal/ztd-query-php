<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

/**
 * The pattern-matching keywords, as PostgreSQL's `A_Expr_Kind` distinguishes them.
 *
 * Source: https://www.postgresql.org/docs/17/functions-matching.html.
 *
 * @visibility SqlSemantics
 */
enum PatternOperator
{
    /**
     * `LIKE`, the operator `~~`.
     */
    case Like;

    /**
     * `ILIKE`, the operator `~~*`.
     */
    case ILike;

    /**
     * `SIMILAR TO`, a regular expression match through `similar_to_escape`.
     */
    case SimilarTo;
}
