<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

/**
 * Which underlying table of a MERGE table receives inserted rows.
 *
 * @visibility public
 * @example Reading the insert method
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT) ENGINE=MERGE UNION=(a, b) INSERT_METHOD=LAST')->tables[0];
 *     $table->properties->insertMethod // => \SqlSemantics\Schema\Table\MergeInsertMethod::Last
 */
enum MergeInsertMethod: string
{
    case No = 'NO';
    case First = 'FIRST';
    case Last = 'LAST';
}
