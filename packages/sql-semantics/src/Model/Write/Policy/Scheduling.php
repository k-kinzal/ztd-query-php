<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

/**
 * Scheduling alternatives.
 *
 * @visibility public
 * @example Reading a scheduling modifier
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT DELAYED INTO t VALUES(1)');
 *     $statement->policy->scheduling // => \SqlSemantics\Model\Write\Policy\Scheduling::Delayed
 */
enum Scheduling: string
{
    case Default = '';
    case LowPriority = 'LOW_PRIORITY';
    case HighPriority = 'HIGH_PRIORITY';
    case Delayed = 'DELAYED';
}
