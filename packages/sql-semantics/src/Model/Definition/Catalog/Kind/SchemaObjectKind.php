<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog\Kind;

/**
 * Schema-scoped PostgreSQL object classes that are addressed by a possibly qualified name.
 * @visibility public
 * @example Reading the SQL spelling of a text search object class
 *     \SqlSemantics\Model\Definition\Catalog\Kind\SchemaObjectKind::TextSearchParser->value // => 'TEXT SEARCH PARSER'
 */
enum SchemaObjectKind: string
{
    case Collation = 'COLLATION';
    case Conversion = 'CONVERSION';
    case Statistics = 'STATISTICS';
    case TextSearchParser = 'TEXT SEARCH PARSER';
    case TextSearchDictionary = 'TEXT SEARCH DICTIONARY';
    case TextSearchTemplate = 'TEXT SEARCH TEMPLATE';
    case TextSearchConfiguration = 'TEXT SEARCH CONFIGURATION';
}
