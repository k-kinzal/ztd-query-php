<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use Closure;

/**
 * Answers what it still costs to finish a rule or a production.
 *
 * A derivation has to stop, and to stop it has to know which way out is
 * shortest. Two measures of "shortest" matter: how many tokens will be
 * written, which decides how long the SQL gets, and how many rules will be
 * expanded, which decides whether the walk fits in its step budget. Both are
 * asked of the same grammar, so both are answered here.
 */
final class TerminationAnalyzer
{
    private TerminationCost $lengths;

    private TerminationCost $steps;

    /**
     * @param Grammar $grammar Grammar whose rules are being costed
     * @param (callable(string): bool)|null $terminalSupported Answers whether a terminal can be written at all, or null when every terminal can
     */
    public function __construct(Grammar $grammar, ?callable $terminalSupported = null)
    {
        $supported = $terminalSupported !== null
            ? Closure::fromCallable($terminalSupported)
            : static fn (string $terminal): bool => true;
        $this->lengths = new TerminationCost($grammar, $supported, 1, 0);
        $this->steps = new TerminationCost($grammar, $supported, 0, 1);
    }

    /**
     * Answers the fewest tokens a non-terminal can be finished in.
     *
     * @param string $nonTerminal Rule to measure
     *
     * @return int Fewest tokens, or PHP_INT_MAX when it can never be finished
     */
    public function getMinLength(string $nonTerminal): int
    {
        return $this->lengths->of($nonTerminal);
    }

    /**
     * Answers the fewest tokens a production can be finished in.
     *
     * @param Production $production Production to measure
     *
     * @return int Fewest tokens, or PHP_INT_MAX when it can never be finished
     */
    public function estimateProductionLength(Production $production): int
    {
        return $this->lengths->ofProduction($production);
    }

    /**
     * Answers the fewest rule expansions a production can be finished in.
     *
     * @param Production $production Production to measure
     *
     * @return int Fewest expansions, or PHP_INT_MAX when it can never be finished
     */
    public function estimateProductionSteps(Production $production): int
    {
        return $this->steps->ofProduction($production);
    }

    /**
     * Reports whether a production can be finished at all.
     *
     * A production whose shortest completion is unbounded contains a rule that
     * can only expand into itself, so a walk that took it would never arrive at
     * terminals.
     *
     * @param Production $production Production to judge
     *
     * @return bool True when some derivation of it ends in terminals
     */
    public function isProductionViable(Production $production): bool
    {
        return $this->estimateProductionLength($production) !== PHP_INT_MAX;
    }
}
