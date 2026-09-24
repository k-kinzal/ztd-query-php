<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Index;

/**
 * NullOrder alternatives.
 *
 * @visibility public
 * @example Classifying the NULL ordering of a key
 *     $key = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE INDEX ix ON t(id NULLS FIRST)')->tables[0]->indexes[0]->elements[0];
 *     $key->nulls // => \SqlSemantics\Schema\Index\NullOrder::First
 */
enum NullOrder: string
{
    case First = 'FIRST';
    case Last = 'LAST';
}
