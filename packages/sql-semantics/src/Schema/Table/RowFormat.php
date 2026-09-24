<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

/**
 * RowFormat alternatives.
 *
 * @visibility public
 * @example Classifying ROW_FORMAT
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT) ROW_FORMAT=DYNAMIC')->tables[0];
 *     $table->properties->rowFormat // => \SqlSemantics\Schema\Table\RowFormat::Dynamic
 */
enum RowFormat: string
{
    case Default = 'default';
    case Dynamic = 'dynamic';
    case Fixed = 'fixed';
    case Compressed = 'compressed';
    case Redundant = 'redundant';
    case Compact = 'compact';
}
