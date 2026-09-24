<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Optimization;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * SQLite INDEXED BY: the table is read only through the named index, and the statement fails when that index cannot be used.
 *
 * @visibility public
 * @example Reading the required index of a table
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(a INTEGER); CREATE INDEX ix ON t(a)'));
 *     $binder->bind('SELECT a FROM t INDEXED BY ix')->from->indexing->index // => 'ix'
 * @example Rejecting an empty index name
 *     new \SqlSemantics\Model\Query\Optimization\IndexedBy(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class IndexedBy implements IndexDirective
{
    /**
     * @param string $index Unqualified index name
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $index)
    {
        if ($index === '') {
            throw new InvalidStructure('INDEXED BY names its index.');
        }
    }
}
