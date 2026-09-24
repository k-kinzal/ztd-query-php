<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Index;

/**
 * Kind alternatives.
 *
 * @visibility public
 * @example Classifying a full-text index
 *     $index = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(body TEXT); CREATE FULLTEXT INDEX ix ON t(body)')->tables[0]->indexes[0];
 *     $index->properties->kind // => \SqlSemantics\Schema\Index\Kind::FullText
 */
enum Kind: string
{
    case Ordinary = 'ordinary';
    case FullText = 'fulltext';
    case Spatial = 'spatial';
}
