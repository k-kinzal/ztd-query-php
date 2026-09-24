<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The position of a new trigger relative to a named existing trigger.
 * @visibility public
 * @example Reading the anchor trigger
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW PRECEDES first SET NEW.n = 1');
 *     $statement->order->trigger // => 'first'
 */
final class TriggerOrder
{
    /**
     * Requires the anchor trigger's nonempty name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly TriggerOrdering $position, public readonly string $trigger)
    {
        if ($trigger === '') {
            throw new InvalidStructure('A trigger order requires the name of another trigger.');
        }
    }
}
