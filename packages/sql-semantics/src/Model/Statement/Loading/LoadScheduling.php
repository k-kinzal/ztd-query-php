<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

/**
 * The table lock a row load requests: concurrent inserts beside readers, or waiting until no client reads the table.
 * @visibility public
 * @example Reading the requested scheduling
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     (new \SqlSemantics\Binder($schema))->bind("LOAD DATA LOW_PRIORITY INFILE 'rows.txt' INTO TABLE t")->scheduling // => \SqlSemantics\Model\Statement\Loading\LoadScheduling::LowPriority
 */
enum LoadScheduling: string
{
    case Concurrent = 'CONCURRENT';
    case LowPriority = 'LOW_PRIORITY';
}
