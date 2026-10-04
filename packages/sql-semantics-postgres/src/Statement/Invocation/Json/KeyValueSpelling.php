<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json;

/**
 * How a JSON key is joined to its value: `key VALUE value` or `key : value`.
 *
 * Both build the same `JsonPair` node; the key of the VALUE spelling is a
 * primary expression.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE.
 *
 * @visibility public
 * @example Spelling the colon form
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\KeyValueSpelling::Colon->value // => ':'
 */
enum KeyValueSpelling: string
{
    case Value = 'VALUE';
    case Colon = ':';
}
