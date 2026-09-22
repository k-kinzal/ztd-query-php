<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Ordering;

use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Resolves ordering positions and aliases against a query's derived result.
 * @visibility SqlSemantics
 */
final class ResultOrdering
{
    /**
     * @param list<Ordering> $ordering Ordering specifications, possibly from a previous immutable query
     * @param list<OutputColumn> $outputs Newly derived output positions
     * @return list<Ordering>
     * @throws InvalidStructure
     */
    public static function bind(array $ordering, array $outputs): array
    {
        $result = [];
        foreach ($ordering as $item) {
            $key = $item->key;
            if ($key instanceof OutputPosition) {
                $output = $outputs[$key->output->ordinal] ?? null;
                if ($output === null) {
                    throw new InvalidStructure('An ordering position must identify a result column.');
                }
                $key = new OutputPosition($output);
            } elseif ($key instanceof OutputAlias) {
                $matches = array_values(array_filter($outputs, static fn (OutputColumn $output): bool => $output->name === $key->output->name));
                if (count($matches) !== 1) {
                    throw new InvalidStructure('An ordering alias must identify exactly one result column.');
                }
                $key = new OutputAlias($matches[0]);
            }
            $result[] = new Ordering($key, $item->descending, $item->nullsFirst);
        }
        return $result;
    }
}
