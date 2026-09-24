<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * One change requested by a PostgreSQL ALTER TABLE-family statement; each form owns exactly its operands.
 * @visibility public
 * @example Reading an ordered action list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t SET LOGGED, CLUSTER ON ix');
 *     $statement->actions[1] instanceof \SqlSemantics\Model\Definition\RelationAction // => true
 */
interface RelationAction
{
}
