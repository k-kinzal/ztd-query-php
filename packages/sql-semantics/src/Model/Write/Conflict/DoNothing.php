<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

use Override;

/**
 * DoNothing carries no UPDATE assignments or row predicate.
 * @visibility public
  * @example Inspecting DoNothing
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING');
 *     $statement->conflicts[0] instanceof \SqlSemantics\Model\Write\Conflict\DoNothing // => true
 */
final class DoNothing extends \SqlSemantics\Model\Write\ConflictAction
{
    #[Override]
    protected function operation(): ActionKind
    {
        return ActionKind::Nothing;
    }
}
