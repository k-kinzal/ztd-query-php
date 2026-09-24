<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Index;

/**
 * Direction alternatives.
 *
 * @visibility public
 * @example Classifying the key ordering
 *     $key = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE INDEX ix ON t(id DESC)')->tables[0]->indexes[0]->elements[0];
 *     $key->direction // => \SqlSemantics\Schema\Index\Direction::Descending
 */
enum Direction: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
