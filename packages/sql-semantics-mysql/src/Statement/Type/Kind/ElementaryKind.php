<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * The types written as one keyword with at most a length: BOOL, SERIAL, JSON, BIT and VECTOR.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/data-types.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Json->value // => 'JSON'
 */
enum ElementaryKind: string
{
    case Boolean = 'BOOL';
    case Serial = 'SERIAL';
    case Json = 'JSON';
    case Bit = 'BIT';
    case Vector = 'VECTOR';
}
