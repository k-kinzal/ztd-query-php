<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json;

/**
 * What a JSON constructor does with NULL values: `NULL ON NULL` keeps them, `ABSENT ON NULL` leaves them out.
 *
 * The default differs between JSON_OBJECT (NULL ON NULL) and JSON_ARRAY
 * (ABSENT ON NULL), so the clause written is kept.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE.
 *
 * @visibility public
 * @example Spelling the clause that leaves NULL values out
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling::Absent->value // => 'ABSENT'
 */
enum JsonNullHandling: string
{
    case Keep = 'NULL';
    case Absent = 'ABSENT';
}
