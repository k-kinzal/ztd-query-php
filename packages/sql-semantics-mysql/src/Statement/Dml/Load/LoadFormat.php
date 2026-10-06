<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

/**
 * What LOAD reads: delimited text (LOAD DATA) or XML (LOAD XML).
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html, https://dev.mysql.com/doc/refman/8.4/en/load-xml.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Xml->value // => 'XML'
 */
enum LoadFormat: string
{
    case Data = 'DATA';
    case Xml = 'XML';
}
