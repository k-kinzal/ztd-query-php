<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

/**
 * Conflict selection, represented by AnyConflict, IndexConflict, or ConstraintConflict.
 * @visibility public
 * @example Inspecting a conflict selector
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t VALUES(1) ON CONFLICT(id) DO UPDATE SET id=2');
 *     $statement->conflicts[0]->target instanceof \SqlSemantics\Model\Write\Conflict\Target // => true
 */
interface Target
{
}
