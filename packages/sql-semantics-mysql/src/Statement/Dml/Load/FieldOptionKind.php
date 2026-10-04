<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

/**
 * What a field option of a text file format sets: the field terminator, the enclosing character or the escape character.
 *
 * Each case holds the keywords it is written with before BY.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind::OptionallyEnclosed->value // => 'OPTIONALLY ENCLOSED'
 */
enum FieldOptionKind: string
{
    case Terminated = 'TERMINATED';
    case OptionallyEnclosed = 'OPTIONALLY ENCLOSED';
    case Enclosed = 'ENCLOSED';
    case Escaped = 'ESCAPED';
}
