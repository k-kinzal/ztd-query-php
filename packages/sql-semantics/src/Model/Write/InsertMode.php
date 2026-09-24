<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

/**
 * Whether conflicting records are replaced as part of insertion.
 * @visibility public
 * @example Reading the insertion mode
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('REPLACE INTO t VALUES(1)');
 *     $statement->mode // => \SqlSemantics\Model\Write\InsertMode::Replace
 */
enum InsertMode: string
{
    case Insert = 'INSERT';
    case Replace = 'REPLACE';
}
