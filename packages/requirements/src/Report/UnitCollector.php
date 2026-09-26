<?php

declare(strict_types=1);

namespace Requirements\Report;

use Requirements\Model\Source;
use Requirements\Source\Unit;
use RuntimeException;

/**
 * Gathers the units selected by each declared scope and rejects inconsistent selections.
 *
 * A unit shared by two scopes must have the same text in both, and no scope may select an
 * ancestor of a unit another scope of the same document selected.
 */
final class UnitCollector
{
    /**
     * @var array<string, SourceUnit> The collected units by key
     */
    public array $units = [];

    /**
     * @var array<string, list<string>> The unit keys of each scope by source ID
     */
    public array $scopes = [];

    /**
     * Starts the scope of a source with no units.
     *
     * @param Source $source The source whose scope is selected next
     */
    public function open(Source $source): void
    {
        $this->scopes[$source->id] = [];
    }

    /**
     * Adds the units a scope selected; units added before a rejected unit are kept.
     *
     * @param Source $source The source of the scope
     * @param list<Unit> $selected The selected units
     *
     * @throws RuntimeException When the scope is empty, a unit is blank, its text conflicts or scopes overlap
     */
    public function collect(Source $source, array $selected): void
    {
        if ($selected === []) {
            throw new RuntimeException('Scope selected no source units.');
        }
        foreach ($selected as $unit) {
            $key = $unit->key($source);
            if ($unit->location === '' || $unit->text === '') {
                throw new RuntimeException('Source units must have nonempty locations and text.');
            }
            if (isset($this->units[$key]) && $this->units[$key]->unit->text !== $unit->text) {
                throw new RuntimeException('Conflicting snapshots for the same source unit.');
            }
            foreach ($this->units as $existing) {
                if ($existing->source->uri === $source->uri && $existing->source->format === $source->format
                    && (str_starts_with($unit->location, $existing->unit->location . '/') || str_starts_with($existing->unit->location, $unit->location . '/'))) {
                    throw new RuntimeException('Overlapping ancestor and descendant units across source scopes.');
                }
            }
            $this->units[$key] ??= new SourceUnit($source, $unit);
            $this->scopes[$source->id][] = $key;
        }
        $this->scopes[$source->id] = array_values(array_unique($this->scopes[$source->id]));
    }
}
