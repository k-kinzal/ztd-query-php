<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

/**
 * The keywords a standard date and time literal is written with.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp->value // => 'TIMESTAMP'
 */
enum TemporalForm: string
{
    case Date = 'DATE';
    case Time = 'TIME';
    case Timestamp = 'TIMESTAMP';
}
