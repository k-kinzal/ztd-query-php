<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Xml;

/**
 * The STANDALONE request of XMLROOT: left unchanged when omitted, set to yes or no, or removed with NO VALUE.
 * @visibility public
 * @example Reading the standalone request
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT XMLROOT(XMLPARSE(DOCUMENT '<a/>'), VERSION '1.0', STANDALONE NO VALUE)");
 *     $query->outputs[0]->expression->standalone // => \SqlSemantics\Model\Scalar\Document\Xml\XmlStandalone::NoValue
 */
enum XmlStandalone: string
{
    case Omitted = '';
    case Yes = 'YES';
    case No = 'NO';
    case NoValue = 'NO VALUE';
}
