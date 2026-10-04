<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem;

/**
 * A mistake in an SQL/JSON expression that the server reports while analyzing the statement.
 *
 * Each case carries the server's message, with `%s` for its subjects in order.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING,
 * `transformJsonFuncExpr` and `transformJsonValueExpr` in `src/backend/parser/parse_expr.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading the message of quotes with a wrapper
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblemKind::QuotesWithWrapper->value // => 'SQL/JSON QUOTES behavior must not be specified when WITH WRAPPER is used'
 */
enum JsonProblemKind: string
{
    case InvalidBehavior = 'invalid %s behavior';
    case InvalidColumnBehavior = 'invalid %s behavior for column "%s"';
    case QuotesWithWrapper = 'SQL/JSON QUOTES behavior must not be specified when WITH WRAPPER is used';
    case FormatInReturning = 'cannot specify FORMAT JSON in RETURNING clause of %s()';
    case EncodingWithoutBytea = 'JSON ENCODING clause is only allowed for bytea input type';
    case SubqueryColumns = 'subquery must return only one column';
    case ParsedEncodingWithoutBytea = 'cannot use JSON FORMAT ENCODING clause for non-bytea input types';
}
