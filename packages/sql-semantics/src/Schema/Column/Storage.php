<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * Storage alternatives.
 *
 * @visibility public
 * @example Classifying the STORAGE attribute
 *     $column = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT STORAGE MEMORY)')->tables[0]->columns[0];
 *     $column->attributes->storage // => \SqlSemantics\Schema\Column\Storage::Memory
 */
enum Storage: string
{
    case Default = 'default';
    case Disk = 'disk';
    case Memory = 'memory';
}
