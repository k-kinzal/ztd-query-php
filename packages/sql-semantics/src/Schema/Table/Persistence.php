<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

/**
 * Persistence alternatives.
 *
 * @visibility public
 * @example Classifying an unlogged table
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE UNLOGGED TABLE t(id INTEGER)')->tables[0];
 *     $table->properties->persistence // => \SqlSemantics\Schema\Table\Persistence::Unlogged
 */
enum Persistence: string
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';
    case Unlogged = 'unlogged';
}
