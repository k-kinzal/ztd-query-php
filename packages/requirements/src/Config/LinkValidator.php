<?php

declare(strict_types=1);

namespace Requirements\Config;

use Requirements\Input\InvalidInputException;
use Requirements\Model\Item;
use Requirements\Test\RunnerConfig;

/**
 * Resolves the links between items and from items to runners.
 *
 * Requirement and related links must name other loaded items, a refined requirement must be
 * a sourced requirement, and every linked test must name a configured runner.
 */
final class LinkValidator
{
    /**
     * Checks every link.
     *
     * @param array<string, Item> $items Every item by ID
     * @param array<string, RunnerConfig> $runners Configured runners by name
     *
     * @throws InvalidInputException When a link is dangling, points to the item itself or to the wrong kind
     */
    public function validate(array $items, array $runners): void
    {
        foreach ($items as $item) {
            foreach ([...$item->requirements, ...$item->related] as $id) {
                if (!isset($items[$id]) || $id === $item->id) {
                    throw new InvalidInputException("$item->id: missing or self reference '$id'.");
                }
            }
            foreach ($item->requirements as $id) {
                if ($items[$id]->kind !== 'requirement' || $items[$id]->origin !== 'sourced') {
                    throw new InvalidInputException("$item->id: '$id' must be a sourced requirement.");
                }
            }
            foreach ($item->tests as $test) {
                if (!isset($runners[$test->runner])) {
                    throw new InvalidInputException("$item->id: unknown runner '$test->runner'.");
                }
            }
        }
    }
}
