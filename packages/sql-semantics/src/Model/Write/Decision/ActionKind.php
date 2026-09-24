<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Decision;

/**
 * Closed ActionKind alternatives.
 * @visibility public
 * @example Reading a merge decision's operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED THEN DELETE');
 *     $statement->merge->actions[0]->action // => \SqlSemantics\Model\Write\Decision\ActionKind::Delete
 */
enum ActionKind: string
{
    case Nothing = 'nothing';
    case Update = 'update';
    case Delete = 'delete';
    case Insert = 'insert';
}
