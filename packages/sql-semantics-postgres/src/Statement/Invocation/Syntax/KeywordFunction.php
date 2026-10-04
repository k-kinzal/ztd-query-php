<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax;

/**
 * A function whose name is a keyword the grammar lets be called with plain arguments.
 *
 * `SUBSTRING(...)` and `OVERLAY(...)` with a plain argument list call the
 * function of that name along the search path; `JSON_OBJECT(...)` with a
 * plain argument list calls the legacy `pg_catalog.json_object`.
 * Source: https://www.postgresql.org/docs/17/functions-string.html, https://www.postgresql.org/docs/17/functions-json.html.
 *
 * @visibility public
 * @example Telling which keyword call searches pg_catalog alone
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordFunction::JsonObject->catalogOnly() // => true
 */
enum KeywordFunction: string
{
    case Substring = 'SUBSTRING';
    case Overlay = 'OVERLAY';
    case JsonObject = 'JSON_OBJECT';

    /**
     * Tells whether the server calls the function qualified with `pg_catalog`.
     */
    public function catalogOnly(): bool
    {
        return $this === self::JsonObject;
    }
}
