<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Subscription;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The subscription identity, publication-list, and option rules PostgreSQL applies within one command.
 * @visibility SqlSemantics
 */
final class SubscriptionInvariant
{
    /**
     * A subscription is a PostgreSQL object with a nonempty name.
     * @throws InvalidStructure
     */
    public static function identity(Origin $origin, string $name): void
    {
        if ($origin->dialect !== Dialect::PostgreSql || $name === '') {
            throw new InvalidStructure('A subscription requires PostgreSQL and a nonempty name.');
        }
    }

    /**
     * Publication names are nonempty and distinct.
     * @param list<string> $publications
     * @return non-empty-list<string>
     * @throws InvalidStructure
     */
    public static function publications(array $publications): array
    {
        Collections::strings($publications);
        if (in_array('', $publications, true) || count(array_unique($publications)) !== count($publications)) {
            throw new InvalidStructure('A subscription names each publication once.');
        }
        return Collections::nonEmpty($publications);
    }

    /**
     * A command accepts only its own options.
     * @param list<SubscriptionParameter> $allowed
     * @throws InvalidStructure
     */
    public static function options(SubscriptionOptions $options, array $allowed): void
    {
        foreach ($options->parameters() as $parameter) {
            if (!in_array($parameter, $allowed, true)) {
                throw new InvalidStructure('This subscription command does not accept the ' . $parameter->value . ' option.');
            }
        }
    }

    /**
     * connect = false excludes enabled, create_slot and copy_data = true, and slot_name = NONE requires enabled and create_slot = false.
     * @throws InvalidStructure
     */
    public static function creation(SubscriptionOptions $options): void
    {
        if ($options->connect === false && ($options->enabled === true || $options->createSlot === true || $options->copyData === true)) {
            throw new InvalidStructure('connect = false excludes enabled, create_slot and copy_data = true.');
        }
        if ($options->slotName === NoSlot::None && $options->connect !== false && ($options->enabled !== false || $options->createSlot !== false)) {
            throw new InvalidStructure('slot_name = NONE requires enabled = false and create_slot = false.');
        }
    }
}
