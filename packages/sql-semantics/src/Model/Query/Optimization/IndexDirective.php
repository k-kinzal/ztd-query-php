<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Optimization;

/**
 * A SQLite index directive on one table occurrence, represented by IndexedBy or NotIndexed.
 *
 * @visibility public
 * @example Reading the index directive of a table
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(a INTEGER); CREATE INDEX ix ON t(a)'));
 *     $binder->bind('SELECT a FROM t NOT INDEXED')->from->indexing instanceof \SqlSemantics\Model\Query\Optimization\IndexDirective // => true
 */
interface IndexDirective
{
}
