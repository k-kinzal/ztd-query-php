<?php

declare(strict_types=1);

namespace Requirements\Report;

final class Analysis
{
    /**
     * @param array<string, SourceUnit> $units
     * @param array<string, list<string>> $scopes
     * @param list<string> $errors
     * @param array<string, list<string>> $evidence
     */
    public function __construct(public readonly array $units, public readonly array $scopes, public readonly array $errors, public readonly array $evidence)
    {
    }

    /**
     * @param list<string>|null $keys
     * @return array{total: int, accounted: int, supported: int, unsupported: int, uncovered: int, percentage: float|null}
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
