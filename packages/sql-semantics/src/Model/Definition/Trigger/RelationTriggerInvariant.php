<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Trigger;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The combinations of timing, granularity, events, and transition tables PostgreSQL accepts for a relation trigger.
 * @visibility SqlSemantics
 */
final class RelationTriggerInvariant
{
    /**
     * A relation trigger is a PostgreSQL object with a nonempty name and a PostgreSQL condition.
     * @throws InvalidStructure
     */
    public static function identity(Origin $origin, string $name, ?Expression $condition): void
    {
        if ($origin->dialect !== Dialect::PostgreSql || $name === '' || ($condition !== null && $condition->type->dialect !== Dialect::PostgreSql)) {
            throw new InvalidStructure('A relation trigger requires PostgreSQL, a nonempty name, and a PostgreSQL condition.');
        }
    }

    /**
     * INSTEAD OF fires per row without condition or columns, and TRUNCATE fires per statement.
     * @throws InvalidStructure
     */
    public static function firing(Timing $timing, TriggerEvents $events, TriggerLevel $level, ?Expression $condition): void
    {
        if ($timing === Timing::InsteadOf && ($level !== TriggerLevel::Row || $condition !== null || $events->columns !== [] || $events->has(TriggerEvent::Truncate))) {
            throw new InvalidStructure('An INSTEAD OF trigger fires for each row of an INSERT, UPDATE or DELETE without a condition or column list.');
        }
        if ($events->has(TriggerEvent::Truncate) && $level === TriggerLevel::Row) {
            throw new InvalidStructure('A TRUNCATE trigger fires for each statement.');
        }
    }

    /**
     * Transition tables belong to AFTER triggers of exactly one row change without a column list, one per row version, with distinct names.
     * @param list<TransitionTable> $transitions
     * @throws InvalidStructure
     */
    public static function transitions(Timing $timing, TriggerEvents $events, array $transitions): void
    {
        Collections::objects($transitions, TransitionTable::class);
        if ($transitions === []) {
            return;
        }
        $changes = array_values(array_filter($events->events, static fn (TriggerEvent $event): bool => $event !== TriggerEvent::Truncate));
        if ($timing !== Timing::After || $events->columns !== [] || $events->has(TriggerEvent::Truncate) || count($changes) !== 1) {
            throw new InvalidStructure('Transition tables require an AFTER trigger of exactly one INSERT, UPDATE or DELETE event without a column list.');
        }
        $versions = array_map(static fn (TransitionTable $table): string => $table->version->value, $transitions);
        $names = array_map(static fn (TransitionTable $table): string => $table->name, $transitions);
        if (count(array_unique($versions)) !== count($versions) || count(array_unique($names)) !== count($names)) {
            throw new InvalidStructure('OLD TABLE and NEW TABLE are each named at most once and under different names.');
        }
        foreach ($transitions as $table) {
            if ($changes[0] !== TriggerEvent::Update && $changes[0] !== ($table->version === RowVersion::Old ? TriggerEvent::Delete : TriggerEvent::Insert)) {
                throw new InvalidStructure('OLD TABLE requires a DELETE or UPDATE trigger and NEW TABLE an INSERT or UPDATE trigger.');
            }
        }
    }

    /**
     * The row images a row-level condition can read: OLD unless the trigger fires on INSERT, NEW unless it fires on DELETE.
     * @return list<RowVersion>
     */
    public static function images(TriggerEvents $events, TriggerLevel $level): array
    {
        if ($level === TriggerLevel::Statement) {
            return [];
        }
        return [
            ...($events->has(TriggerEvent::Insert) ? [] : [RowVersion::Old]),
            ...($events->has(TriggerEvent::Delete) ? [] : [RowVersion::New]),
        ];
    }
}
