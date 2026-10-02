<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * The binary string types of MySQL, by the keywords they are written with.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-type-syntax.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind::LongVarBinary->value // => 'LONG VARBINARY'
 */
enum BinaryKind: string
{
    case Binary = 'BINARY';
    case VarBinary = 'VARBINARY';
    case TinyBlob = 'TINYBLOB';
    case Blob = 'BLOB';
    case MediumBlob = 'MEDIUMBLOB';
    case LongBlob = 'LONGBLOB';
    case LongVarBinary = 'LONG VARBINARY';
}
