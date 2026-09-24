<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

/**
 * ConstraintResponse alternatives.
 *
 * @visibility public
 * @example Reading an SQLite conflict resolution
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT OR IGNORE INTO t VALUES(1)');
 *     $statement->policy->onViolation // => \SqlSemantics\Model\Write\Policy\ConstraintResponse::Ignore
 */
enum ConstraintResponse: string
{
    case Default = '';
    case Rollback = 'ROLLBACK';
    case Abort = 'ABORT';
    case Fail = 'FAIL';
    case Ignore = 'IGNORE';
    case Replace = 'REPLACE';
}
