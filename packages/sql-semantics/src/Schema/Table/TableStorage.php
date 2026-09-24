<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

/**
 * Where an NDB table stores its rows (STORAGE DISK or STORAGE MEMORY).
 *
 * @visibility public
 * @example Reading the requested storage
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT) TABLESPACE ts STORAGE DISK')->tables[0];
 *     $table->properties->storage // => \SqlSemantics\Schema\Table\TableStorage::Disk
 */
enum TableStorage: string
{
    case Disk = 'DISK';
    case Memory = 'MEMORY';
}
