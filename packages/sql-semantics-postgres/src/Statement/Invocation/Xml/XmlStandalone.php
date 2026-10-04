<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

/**
 * The standalone declaration XMLROOT sets.
 *
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML-XMLROOT.
 *
 * @visibility public
 * @example Spelling the declaration that removes the standalone setting
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlStandalone::NoValue->value // => 'NO VALUE'
 */
enum XmlStandalone: string
{
    case Yes = 'YES';
    case No = 'NO';
    case NoValue = 'NO VALUE';
}
