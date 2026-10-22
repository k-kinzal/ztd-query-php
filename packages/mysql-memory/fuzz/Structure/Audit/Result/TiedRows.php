<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Result;

use Fuzz\Target\Observer;
use InvalidArgumentException;

/**
 * Compares complete rows within adjacent groups having identical selected sort keys.
 * Distinct key groups retain their order. No metadata, value or row is discarded.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/limit-optimization.html.
 */
final class TiedRows
{
    /**
     * @param array<string, mixed> $observation Complete protocol observation
     * @param non-empty-list<int> $positions Zero-based selected columns comprising every ordering key
     * @return array<string, mixed> Observation canonicalized only within exact ties
     */
    public function comparable(array $observation, array $positions): array
    {
        $results = $observation['results'] ?? null;
        if (!is_array($results)) {
            return $observation;
        }
        foreach ($results as $index => $result) {
            if (is_array($result) && is_array($result['rows'] ?? null)) {
                $result['rows'] = $this->rows($result['rows'], $positions);
                $results[$index] = $result;
            }
        }
        $observation['results'] = $results;
        return $observation;
    }

    /**
     * @param array<mixed> $rows Complete result rows
     * @param non-empty-list<int> $positions Selected columns containing the full ordering key
     * @return list<mixed> Row sequence with each adjacent exact-tie group canonically ordered
     * @throws InvalidArgumentException When a declared ordering key is absent from an observed row
     */
    public function rows(array $rows, array $positions): array
    {
        $groups = [];
        $previous = null;
        foreach ($rows as $row) {
            if (!is_array($row) || array_diff($positions, array_keys($row)) !== []) {
                throw new InvalidArgumentException('Every ordering key must be present in the observed row.');
            }
            $key = serialize(array_intersect_key($row, array_flip($positions)));
            if ($key !== $previous) {
                $groups[] = [];
                $previous = $key;
            }
            $groups[count($groups) - 1][] = $row;
        }
        $ordered = [];
        foreach ($groups as $group) {
            array_push($ordered, ...(new Observer())->bag($group));
        }
        return $ordered;
    }
}
