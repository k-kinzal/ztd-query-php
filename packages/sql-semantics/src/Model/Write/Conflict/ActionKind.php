<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

/**
 * Closed ActionKind alternatives.
 * @visibility public
 * @example Reading a conflict handler's operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING');
 *     $statement->conflicts[0]->action // => \SqlSemantics\Model\Write\Conflict\ActionKind::Nothing
 */
enum ActionKind: string
{
    case Nothing = 'nothing';
    case Update = 'update';
    case Replace = 'replace';
}
