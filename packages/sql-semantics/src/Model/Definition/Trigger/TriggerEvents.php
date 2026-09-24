<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Trigger;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The distinct table changes that fire a relation trigger; an UPDATE may be limited to named columns.
 * @visibility public
 * @example Reading the events of a trigger
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE TRIGGER audit AFTER INSERT OR UPDATE OF a, b ON t EXECUTE FUNCTION log_change()');
 *     $statement->events->events // => [\SqlSemantics\Model\Definition\Trigger\TriggerEvent::Insert, \SqlSemantics\Model\Definition\Trigger\TriggerEvent::Update]
 *     $statement->events->columns // => ['a', 'b']
 *     new \SqlSemantics\Model\Definition\Trigger\TriggerEvents([\SqlSemantics\Model\Definition\Trigger\TriggerEvent::Insert, \SqlSemantics\Model\Definition\Trigger\TriggerEvent::Insert]) // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class TriggerEvents
{
    /**
     * @var non-empty-list<TriggerEvent> Validated distinct events in written order
     */
    public readonly array $events;

    /**
     * @param list<TriggerEvent> $events
     * @param list<string> $columns Columns limiting the UPDATE event
     * @throws InvalidStructure
     */
    public function __construct(array $events, public readonly array $columns = [])
    {
        Collections::objects($events, TriggerEvent::class);
        Collections::strings($columns);
        if (count(array_unique(array_map(static fn (TriggerEvent $event): string => $event->value, $events))) !== count($events)) {
            throw new InvalidStructure('A trigger names each event at most once.');
        }
        if ($columns !== [] && !in_array(TriggerEvent::Update, $events, true)) {
            throw new InvalidStructure('Only an UPDATE event can name columns.');
        }
        if (in_array('', $columns, true) || count(array_unique($columns)) !== count($columns)) {
            throw new InvalidStructure('UPDATE OF names distinct nonempty columns.');
        }
        $this->events = Collections::nonEmpty($events);
    }

    /**
     * Reports whether the trigger fires for the given change.
     */
    public function has(TriggerEvent $event): bool
    {
        return in_array($event, $this->events, true);
    }
}
