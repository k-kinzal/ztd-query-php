<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Runs an event once, at the time the AT expression names when the event is created or altered.
 * @visibility public
 * @example Reading a one-time schedule
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP DO DO 1');
 *     $statement->schedule instanceof \SqlSemantics\Model\Definition\Routine\Stored\OneTimeSchedule // => true
 */
final class OneTimeSchedule
{
    /**
     * Requires a MySQL time expression.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $at)
    {
        if ($at->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('An event schedule requires MySQL expressions.');
        }
    }
}
