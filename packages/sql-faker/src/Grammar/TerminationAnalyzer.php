<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * Analyzes grammar to compute minimum termination lengths for non-terminals.
 *
 * Uses fixed-point iteration to find the shortest derivation path
 * (minimum number of tokens) required to terminate each rule.
 */
final class TerminationAnalyzer
{
    /** @var array<string, int> Non-terminal name => minimum tokens to terminate */
    private array $lengths;

    public function __construct(Grammar $grammar)
    {
        $this->lengths = $this->computeMinTerminationLengths($grammar);
    }

    /**
     * Get the minimum number of tokens required to terminate a non-terminal.
     */
    public function getMinLength(string $nonTerminal): int
    {
        return $this->lengths[$nonTerminal] ?? 1;
    }

    /**
     * Estimate the minimum number of tokens required to terminate a production.
     */
    public function estimateProductionLength(Production $production): int
    {
        $length = 0;
        foreach ($production->symbols as $sym) {
            if ($sym instanceof Terminal) {
                $length += 1;
            } elseif ($sym instanceof NonTerminal) {
                $length += $this->lengths[$sym->value] ?? 1;
            }
        }
        return $length;
    }

    /**
     * @return array<string, int>
     */
    private function computeMinTerminationLengths(Grammar $grammar): array
    {
        $inf = PHP_INT_MAX;

        /** @var array<string, int> $lengths */
        $lengths = [];
        foreach ($grammar->ruleMap as $name => $_rule) {
            $lengths[$name] = $inf;
        }

        $changed = true;
        while ($changed) {
            $changed = false;

            foreach ($grammar->ruleMap as $name => $rule) {
                $best = $lengths[$name];

                foreach ($rule->alternatives as $alt) {
                    $altLength = $this->sumProductionLength($alt->symbols, $lengths);

                    if ($altLength < $best) {
                        $best = $altLength;
                    }
                }

                if ($best !== $lengths[$name]) {
                    $lengths[$name] = $best;
                    $changed = true;
                }
            }
        }

        foreach ($lengths as $name => $value) {
            if ($value === $inf) {
                $lengths[$name] = 1;
            }
        }

        return $lengths;
    }

    /**
     * Sum the minimum token count for a list of symbols given current length estimates.
     * Returns PHP_INT_MAX if any non-terminal has no known finite length.
     *
     * @param list<Symbol> $symbols
     * @param array<string, int> $lengths
     */
    private function sumProductionLength(array $symbols, array $lengths): int
    {
        $total = 0;
        foreach ($symbols as $sym) {
            if ($sym instanceof Terminal) {
                $total++;
                continue;
            }
            if ($sym instanceof NonTerminal) {
                $symLen = $lengths[$sym->value] ?? PHP_INT_MAX;
                if ($symLen === PHP_INT_MAX) {
                    return PHP_INT_MAX;
                }
                $total += $symLen;
            }
        }
        return $total;
    }
}
