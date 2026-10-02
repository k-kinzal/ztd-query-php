<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * How a character type names its character set: ASCII, UNICODE, BYTE, a named character set, or BINARY alone.
 *
 * Each case holds the keywords the type is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-type-syntax.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Named->value // => 'CHARSET'
 */
enum CharsetForm: string
{
    case Ascii = 'ASCII';
    case Unicode = 'UNICODE';
    case Byte = 'BYTE';
    case Named = 'CHARSET';
    case Binary = 'BINARY';
}
