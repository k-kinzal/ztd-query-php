<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

/**
 * The whitespace handling written in XMLPARSE; STRIP WHITESPACE is what the server does when nothing is written.
 *
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML-XMLPARSE (and `xml_whitespace_option` of the grammar).
 *
 * @visibility public
 * @example Spelling the preserving option
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlWhitespace::Preserve->value // => 'PRESERVE'
 */
enum XmlWhitespace: string
{
    case Preserve = 'PRESERVE';
    case Strip = 'STRIP';
}
