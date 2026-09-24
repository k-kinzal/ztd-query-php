<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

/**
 * SQLite column affinity, independent of the runtime storage class.
 * @visibility public
 * @example Deriving column affinity from a declared type
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(a CHARACTER(20), b FLOATING, c INT)')->tables[0];
 *     $table->columns[0]->type->affinity // => \SqlSemantics\Type\Identity\StorageAffinity::Text
 *     $table->columns[1]->type->affinity // => \SqlSemantics\Type\Identity\StorageAffinity::Real
 *     $table->columns[2]->type->affinity // => \SqlSemantics\Type\Identity\StorageAffinity::Integer
 */
enum StorageAffinity: string
{
    case Integer = 'integer';
    case Text = 'text';
    case Blob = 'blob';
    case Real = 'real';
    case Numeric = 'numeric';
}
