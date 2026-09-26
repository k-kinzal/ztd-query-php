<?php

declare(strict_types=1);

namespace Requirements\Report;

/**
 * The result of analyzing a project's sources: units, scopes, errors and resolved evidence.
 */
final class Analysis
{
    /**
     * @param array<string, SourceUnit> $units Every unit in scope by key, with its claims
     * @param array<string, list<string>> $scopes The unit keys of each scope by source ID
     * @param list<string> $errors Scope and evidence failures
     * @param array<string, list<string>> $evidence The quoted unit keys by item ID
     */
    public function __construct(public readonly array $units, public readonly array $scopes, public readonly array $errors, public readonly array $evidence)
    {
    }

    /**
     * Counts how the units are accounted for.
     *
     * @param list<string>|null $keys The unit keys to count; every unit when null
     *
     * @return array{total: int, accounted: int, supported: int, unsupported: int, uncovered: int, percentage: float|null} The counts and the accounted percentage, null without units
     */
    public function summary(?array $keys = null): array
    {
        $keys ??= array_keys($this->units);
        $accounted = 0;
        $supported = 0;
        foreach ($keys as $key) {
            $unit = $this->units[$key];
            $accounted += $unit->claims === [] ? 0 : 1;
            $supported += $unit->supported() ? 1 : 0;
        }
        $count = count($keys);
        return ['total' => $count, 'accounted' => $accounted, 'supported' => $supported, 'unsupported' => $accounted - $supported, 'uncovered' => $count - $accounted, 'percentage' => $count === 0 ? null : 100.0 * $accounted / $count];
    }
}
