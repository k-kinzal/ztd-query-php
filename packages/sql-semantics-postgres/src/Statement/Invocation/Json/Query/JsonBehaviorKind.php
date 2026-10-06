<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query;

/**
 * What an SQL/JSON function returns when the path finds nothing or an error occurs.
 *
 * Mirrors PostgreSQL's `JsonBehaviorType`; the bare EMPTY, which the server
 * treats as EMPTY ARRAY, is a distinct spelling and kept.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING.
 *
 * @visibility public
 * @example Spelling the behavior returning an empty object
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind::EmptyObject->value // => 'EMPTY OBJECT'
 */
enum JsonBehaviorKind: string
{
    case Error = 'ERROR';
    case Null = 'NULL';
    case True = 'TRUE';
    case False = 'FALSE';
    case Unknown = 'UNKNOWN';
    case EmptyArray = 'EMPTY ARRAY';
    case EmptyObject = 'EMPTY OBJECT';
    case Empty = 'EMPTY';
    case Default = 'DEFAULT';
}
