<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use Closure;

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

    /** @var array<string, int> Non-terminal name => minimum derivation steps to terminate */
    private array $steps;

    /** @var Closure(string): bool */
    private Closure $terminalSupported;

    /**
     * @param (callable(string): bool)|null $terminalSupported
     */
    public function __construct(Grammar $grammar, ?callable $terminalSupported = null)
    {
        $this->terminalSupported = $terminalSupported !== null
            ? Closure::fromCallable($terminalSupported)
            : static fn (string $terminal): bool => true;
        $this->lengths = $this->computeMinTerminationLengths($grammar);
        $this->steps = $this->computeMinTerminationSteps($grammar);
    }

    /**
     * Get the minimum number of tokens required to terminate a non-terminal.
     */
    public function getMinLength(string $nonTerminal): int
    {
        return $this->lengths[$nonTerminal] ?? (($this->terminalSupported)($nonTerminal) ? 1 : PHP_INT_MAX);
    }

    /**
     * Estimate the minimum number of tokens required to terminate a production.
     */
    public function estimateProductionLength(Production $production): int
    {
        return $this->sumProductionLength($production->symbols, $this->lengths);
    }

    /**
     * Estimate the minimum number of non-terminal expansions required to terminate a production.
     */
    public function estimateProductionSteps(Production $production): int
    {
        return $this->sumProductionSteps($production->symbols, $this->steps);
    }

    public function isProductionViable(Production $production): bool
    {
        return $this->estimateProductionLength($production) !== PHP_INT_MAX;
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

        return $lengths;
    }

    /**
     * @return array<string, int>
     */
    private function computeMinTerminationSteps(Grammar $grammar): array
    {
        $steps = array_fill_keys(array_keys($grammar->ruleMap), PHP_INT_MAX);

        $changed = true;
        while ($changed) {
            $changed = false;

            foreach ($grammar->ruleMap as $name => $rule) {
                $best = $steps[$name];

                foreach ($rule->alternatives as $alternative) {
                    $remaining = $this->sumProductionSteps($alternative->symbols, $steps);
                    if ($remaining !== PHP_INT_MAX && $remaining < PHP_INT_MAX - 1) {
                        $best = min($best, $remaining + 1);
                    }
                }

                if ($best !== $steps[$name]) {
                    $steps[$name] = $best;
                    $changed = true;
                }
            }
        }

        return $steps;
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
                if (!(($this->terminalSupported)($sym->value))) {
                    return PHP_INT_MAX;
                }
                $total++;
                continue;
            }
            if ($sym instanceof NonTerminal) {
                $symLen = $lengths[$sym->value]
                    ?? (($this->terminalSupported)($sym->value) ? 1 : PHP_INT_MAX);
                if ($symLen === PHP_INT_MAX) {
                    return PHP_INT_MAX;
                }
                if ($total > PHP_INT_MAX - $symLen) {
                    return PHP_INT_MAX;
                }
                $total += $symLen;
            }
        }
        return $total;
    }

    /**
     * @param list<Symbol> $symbols
     * @param array<string, int> $steps
     */
    private function sumProductionSteps(array $symbols, array $steps): int
    {
        $total = 0;
        foreach ($symbols as $symbol) {
            if ($symbol instanceof Terminal) {
                if (!(($this->terminalSupported)($symbol->value))) {
                    return PHP_INT_MAX;
                }
                continue;
            }

            if (!$symbol instanceof NonTerminal) {
                continue;
            }

            $symbolSteps = $steps[$symbol->value]
                ?? (($this->terminalSupported)($symbol->value) ? 1 : PHP_INT_MAX);
            if ($symbolSteps === PHP_INT_MAX || $total > PHP_INT_MAX - $symbolSteps) {
                return PHP_INT_MAX;
            }
            $total += $symbolSteps;
        }

        return $total;
    }
}
