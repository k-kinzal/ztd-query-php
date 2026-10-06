<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query;

use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * The SQL/JSON query functions of PostgreSQL 17.
 *
 * Mirrors PostgreSQL's `JsonExprOp` (without the JSON_TABLE operation,
 * which is a relation).
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING.
 *
 * @visibility public
 * @example Reading the default result type of JSON_VALUE
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonFunctionKind::Value->builtin() // => \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text
 */
enum JsonFunctionKind: string
{
    case Exists = 'JSON_EXISTS';
    case Query = 'JSON_QUERY';
    case Value = 'JSON_VALUE';

    /**
     * Answers the result type when no RETURNING clause is written.
     */
    public function builtin(): Builtin
    {
        return match ($this) {
            self::Exists => Builtin::Bool,
            self::Query => Builtin::Jsonb,
            self::Value => Builtin::Text,
        };
    }
}
