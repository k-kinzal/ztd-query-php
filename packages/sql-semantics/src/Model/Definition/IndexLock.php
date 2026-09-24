<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * Requested table lock level for a MySQL index operation.
 * @visibility public
 * @example Reading the requested lock level
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DROP INDEX ix ON t ALGORITHM=INPLACE LOCK=NONE', strict: false);
 *     $statement->lock // => \SqlSemantics\Model\Definition\IndexLock::None
 */
enum IndexLock: string
{
    case Default = 'DEFAULT';
    case None = 'NONE';
    case Shared = 'SHARED';
    case Exclusive = 'EXCLUSIVE';
}
