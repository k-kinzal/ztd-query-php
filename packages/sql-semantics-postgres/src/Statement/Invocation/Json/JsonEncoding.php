<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json;

/**
 * A character encoding named in FORMAT JSON ENCODING.
 *
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE.
 *
 * @visibility public
 * @example Reading the encoding a name selects
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonEncoding::named('UTF8') // => \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonEncoding::Utf8
 */
enum JsonEncoding: string
{
    case Utf8 = 'utf8';
    case Utf16 = 'utf16';
    case Utf32 = 'utf32';

    /**
     * Answers the encoding a name selects, compared without case as the server does; null for any other name.
     */
    public static function named(string $name): ?self
    {
        return self::tryFrom(strtolower($name));
    }
}
