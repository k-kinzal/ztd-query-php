<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Locking;

/**
 * Applies a locking clause to all eligible relations in this query scope.
 * @visibility public
  * @example Inspecting AllRowLock
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(id INTEGER)'));
 *     $statement = $binder->bind('SELECT id FROM t LOCK IN SHARE MODE');
 *     $statement->locks[0] instanceof \SqlSemantics\Model\Query\Locking\AllRowLock // => true
 */
final class AllRowLock extends RowLock
{
}
