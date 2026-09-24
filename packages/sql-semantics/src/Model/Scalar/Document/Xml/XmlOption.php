<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Xml;

/**
 * Whether XMLPARSE or XMLSERIALIZE treats the value as a well-formed document or as a content fragment.
 * @visibility public
 * @example Reading the option of XMLPARSE
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT XMLPARSE(CONTENT 'a<b/>')");
 *     $query->outputs[0]->expression->option // => \SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Content
 */
enum XmlOption: string
{
    case Document = 'DOCUMENT';
    case Content = 'CONTENT';
}
