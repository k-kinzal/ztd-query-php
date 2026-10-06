<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * The character string types of MySQL, by the keywords they are written with.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-type-syntax.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::LongVarChar->value // => 'LONG VARCHAR'
 */
enum CharacterKind: string
{
    case Char = 'CHAR';
    case VarChar = 'VARCHAR';
    case CharVarying = 'CHAR VARYING';
    case TinyText = 'TINYTEXT';
    case Text = 'TEXT';
    case MediumText = 'MEDIUMTEXT';
    case LongText = 'LONGTEXT';
    case Long = 'LONG';
    case LongVarChar = 'LONG VARCHAR';
    case LongCharVarying = 'LONG CHAR VARYING';
}
