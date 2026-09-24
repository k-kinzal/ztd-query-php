<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * GeneratedStorage alternatives.
 *
 * @visibility public
 * @example Distinguishing virtual from stored generated columns
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT, b INT AS (a + 1), c INT AS (a + 2) STORED)')->tables[0];
 *     $table->columns[1]->generation->storage // => \SqlSemantics\Schema\Column\GeneratedStorage::Virtual
 *     $table->columns[2]->generation->storage // => \SqlSemantics\Schema\Column\GeneratedStorage::Stored
 */
enum GeneratedStorage: string
{
    case Virtual = 'virtual';
    case Stored = 'stored';
}
