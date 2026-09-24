<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * Format alternatives.
 *
 * @visibility public
 * @example Classifying COLUMN_FORMAT
 *     $column = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT COLUMN_FORMAT DYNAMIC)')->tables[0]->columns[0];
 *     $column->attributes->format // => \SqlSemantics\Schema\Column\Format::Dynamic
 */
enum Format: string
{
    case Default = 'default';
    case Fixed = 'fixed';
    case Dynamic = 'dynamic';
}
