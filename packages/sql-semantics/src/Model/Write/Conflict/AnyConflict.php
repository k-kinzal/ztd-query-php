<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

/**
 * A handler applying without an explicit index or constraint selector.
 * @visibility public
  * @example Inspecting AnyConflict
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
 *     $nothing = $binder->bind('INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING')->conflicts[0];
 *     $update = $binder->bind('INSERT INTO t VALUES(1) ON CONFLICT(id) DO UPDATE SET id=2 WHERE t.id=1')->conflicts[0];
 *     $nothing->target instanceof \SqlSemantics\Model\Write\Conflict\AnyConflict // => true
 */
final class AnyConflict implements Target
{
}
