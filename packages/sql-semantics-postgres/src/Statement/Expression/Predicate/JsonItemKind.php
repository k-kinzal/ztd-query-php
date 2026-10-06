<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate;

/**
 * The kinds of JSON item an IS JSON predicate tests for, as PostgreSQL's `JsonValueType` distinguishes them.
 *
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-MISC.
 *
 * @visibility public
 * @example Reading the tested kind
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT '[]' IS JSON ARRAY");
 *     $query->field(0)->expression->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonItemKind::JsonArray
 */
enum JsonItemKind: string
{
    /**
     * `JSON`: any JSON value.
     */
    case Json = 'JSON';

    /**
     * `JSON VALUE`: any JSON value, written explicitly.
     */
    case JsonValue = 'JSON VALUE';

    /**
     * `JSON ARRAY`.
     */
    case JsonArray = 'JSON ARRAY';

    /**
     * `JSON OBJECT`.
     */
    case JsonObject = 'JSON OBJECT';

    /**
     * `JSON SCALAR`.
     */
    case JsonScalar = 'JSON SCALAR';
}
