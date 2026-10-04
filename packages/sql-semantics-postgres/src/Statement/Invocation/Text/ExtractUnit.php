<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text;

/**
 * A field of EXTRACT written as one of the keywords the grammar reserves for it.
 *
 * The server passes the field to `extract` as the lower-case string.
 * Source: https://www.postgresql.org/docs/17/functions-datetime.html#FUNCTIONS-DATETIME-EXTRACT.
 *
 * @visibility public
 * @example Reading the field a keyword passes
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\ExtractUnit::Minute->field() // => 'minute'
 */
enum ExtractUnit: string
{
    case Year = 'YEAR';
    case Month = 'MONTH';
    case Day = 'DAY';
    case Hour = 'HOUR';
    case Minute = 'MINUTE';
    case Second = 'SECOND';

    /**
     * Answers the field name the server passes.
     */
    public function field(): string
    {
        return strtolower($this->value);
    }
}
