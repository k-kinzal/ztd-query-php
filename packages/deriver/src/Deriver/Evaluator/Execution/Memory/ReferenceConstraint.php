<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Memory;

use Deriver\Deriver\Evaluator\Term;

/**
 * Finds the live typed properties sharing a reference cell after alias rebinding.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class ReferenceConstraint
{
    /**
     * Returns every property type that constrains a write through an escaped cell.
     * @param Memory $memory Current heap and alias graph
     * @param Location $location Written address
     * @return list<string> Simultaneous declaration constraints
     */
    public function find(Memory $memory, Location $location): array
    {
        $types = [];
        if (count($location->path) === 1 && isset($memory->propertyTypes[$location->root][$location->path[0]])) {
            $types[$memory->propertyTypes[$location->root][$location->path[0]]] = true;
        }
        $cell = $this->cell($memory, $location);
        if ($cell === null) {
            return array_keys($types);
        }
        foreach ($memory->propertyTypes as $root => $properties) {
            foreach ($properties as $slot => $type) {
                if ($this->cell($memory, new Location($root, [$slot])) === $cell) {
                    $types[$type] = true;
                }
            }
        }
        return array_keys($types);
    }

    /**
     * Resolves a whole-cell address without allocating or changing an alias.
     * @param Memory $memory Current storage
     * @param Location $location Candidate address
     * @return string|null Canonical reference root, or null for an ordinary array element
     */
    public function cell(Memory $memory, Location $location): ?string
    {
        if ($location->unknown) {
            return null;
        }
        $raw = $memory->raw($location);
        $root = $location->path === [] ? $location->root : null;
        $seen = [];
        while ($raw->kind === \Deriver\Deriver\Evaluator\TermKind::Cell && is_string($raw->literal) && !isset($seen[$raw->literal])) {
            $root = $raw->literal;
            $seen[$root] = true;
            $raw = $memory->cells[$root] ?? new Term(\Deriver\Deriver\Evaluator\TermKind::Uninitialized);
        }
        return $root;
    }
}
