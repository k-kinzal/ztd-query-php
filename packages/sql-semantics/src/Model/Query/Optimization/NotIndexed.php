<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Optimization;

/**
 * SQLite NOT INDEXED: the table is read without any index other than its rowid or primary key.
 *
 * @visibility public
 * @example Reading a table that must not use an index
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(a INTEGER)'));
 *     $binder->bind('DELETE FROM t NOT INDEXED WHERE a = 1')->target->indexing instanceof \SqlSemantics\Model\Query\Optimization\NotIndexed // => true
 */
final class NotIndexed implements IndexDirective
{
}
