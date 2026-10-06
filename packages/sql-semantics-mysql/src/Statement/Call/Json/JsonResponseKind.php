<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json;

/**
 * What JSON_VALUE and a JSON_TABLE column return when the path finds nothing or an error occurs.
 *
 * Each case holds the keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#function_json-value.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind::Default->value // => 'DEFAULT'
 */
enum JsonResponseKind: string
{
    case Error = 'ERROR';
    case Null = 'NULL';
    case Default = 'DEFAULT';
}
