<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

/**
 * The indentation written in XMLSERIALIZE (PostgreSQL 16 and later); NO INDENT is what the server does when nothing is written.
 *
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-SERIALIZE.
 *
 * @visibility public
 * @example Spelling the indenting option
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlIndent::NoIndent->value // => 'NO INDENT'
 */
enum XmlIndent: string
{
    case Indent = 'INDENT';
    case NoIndent = 'NO INDENT';
}
