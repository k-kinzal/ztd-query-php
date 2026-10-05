<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * How a character type names its character set: ASCII, UNICODE, BYTE, a named character set, or BINARY alone.
 *
 * Each case holds the keywords the type is written with. A named character
 * set is written `CHARSET name` or `CHARACTER SET name` (also `CHAR SET`);
 * both are kept, since they are part of the text MySQL names an unaliased
 * select list expression after.
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
    case CharacterSet = 'CHARACTER SET';
    case Binary = 'BINARY';

    /**
     * Tells whether the form names a character set.
     *
     * @example Telling the named forms
     *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::CharacterSet->named() // => true
     */
    public function named(): bool
    {
        return $this === self::Named || $this === self::CharacterSet;
    }
}
