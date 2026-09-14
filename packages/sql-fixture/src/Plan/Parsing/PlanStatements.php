<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Parsing;

/**
 * Splits DBML at separators outside groups and composite keys.
 *
 * @visibility root
 */
final class PlanStatements
{
    /**
     * Split on commas and newlines that are not inside brackets.
     *
     * @return array<int, string>
     * @throws \SqlFixture\Plan\Exception\UnbalancedBracketsException
     */
    public function split(string $plan): array
    {
        $statements = [];
        $current = '';
        $depth = 0;

        foreach (str_split($plan) as $character) {
            if ($character === '[' || $character === '(') {
                $depth++;
            } elseif ($character === ']' || $character === ')') {
                $depth--;
            }

            if ($depth < 0) {
                throw new \SqlFixture\Plan\Exception\UnbalancedBracketsException($plan);
            }

            if ($depth === 0 && ($character === ',' || $character === "\n" || $character === ';')) {
                $statements[] = $current;
                $current = '';
                continue;
            }

            $current .= $character;
        }

        $statements[] = $current;

        return array_filter(
            array_map('trim', $statements),
            static fn (string $statement): bool => $statement !== ''
        );
    }
}
