<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

/**
 * What a load does with an input row whose key duplicates an existing row: replace the existing row or skip the input row.
 * @visibility public
 * @example Reading the duplicate handling of a load
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     (new \SqlSemantics\Binder($schema))->bind("LOAD DATA INFILE 'rows.txt' IGNORE INTO TABLE t")->duplicates // => \SqlSemantics\Model\Statement\Loading\DuplicateRows::Ignore
 */
enum DuplicateRows: string
{
    case Replace = 'REPLACE';
    case Ignore = 'IGNORE';
}
