<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

/**
 * Whether character data is read as an XML document or as XML content.
 *
 * Source: https://www.postgresql.org/docs/17/datatype-xml.html.
 *
 * @visibility public
 * @example Spelling the document choice
 *     \SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption::Document->value // => 'DOCUMENT'
 */
enum XmlOption: string
{
    case Document = 'DOCUMENT';
    case Content = 'CONTENT';
}
