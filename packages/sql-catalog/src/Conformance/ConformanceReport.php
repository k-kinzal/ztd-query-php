<?php

declare(strict_types=1);

namespace SqlCatalog\Conformance;

/**
 * How a catalog held up against the statements a program actually sent.
 *
 * @visibility root
 */
final class ConformanceReport
{
    /**
     * @param int $observed How many statements the program sent
     * @param int $covered How many of them some catalogued statement matches
     * @param int $resolved How many of them a fully resolved catalogued statement matches
     * @param list<ObservedStatement> $uncovered The statements nothing in the catalog matches
     * @param list<string> $valueMismatches Values the catalog said a parameter could not take
     */
    public function __construct(
        public readonly int $observed,
        public readonly int $covered,
        public readonly int $resolved,
        public readonly array $uncovered,
        public readonly array $valueMismatches,
    ) {
    }

    /**
     * Whether every statement the program sent is in the catalog.
     *
     * This is the property the analysis has to hold: it may report statements a
     * run never reaches, but it may not miss one the run does.
     */
    public function isSound(): bool
    {
        return $this->uncovered === [] && $this->valueMismatches === [];
    }

    /**
     * The share of sent statements the catalog matches, from zero to one.
     */
    public function coverage(): float
    {
        return $this->observed === 0 ? 1.0 : $this->covered / $this->observed;
    }

    /**
     * The share of sent statements the catalog matches without any gaps, from zero to one.
     */
    public function precision(): float
    {
        return $this->observed === 0 ? 1.0 : $this->resolved / $this->observed;
    }

    /**
     * The report written for a reader.
     */
    public function display(): string
    {
        return sprintf(
            '%d observed, %d covered (%.1f%%), %d fully resolved (%.1f%%), %d value mismatch(es)',
            $this->observed,
            $this->covered,
            $this->coverage() * 100,
            $this->resolved,
            $this->precision() * 100,
            count($this->valueMismatches),
        );
    }
}
