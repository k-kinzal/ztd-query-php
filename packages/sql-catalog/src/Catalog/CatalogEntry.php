<?php

declare(strict_types=1);

namespace SqlCatalog\Catalog;

use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\TextPattern;

/**
 * One syntactic SQL candidate. Runtime reachability is not assessed.
 *
 * @visibility root
 */
final class CatalogEntry
{
    /**
     * @param string $id The identifier the statement keeps across runs
     * @param StatementKind $kind What the statement does
     * @param TextPattern $pattern The statement text, with a gap wherever a value is spliced in
     * @param list<string> $tables The tables the statement names
     * @param list<Placeholder> $placeholders The bind parameters, in order
     * @param CallSite $site Where the statement is issued
     * @param list<Finding> $findings What is worth reporting about the statement
     * @param bool $correlated Whether values kept their structural pairing, without a reachability guarantee
     * @param list<string> $through The path taken to this reading, from the body the walk started in, outermost first
     * @param bool $truncated Whether a bound — on loop passes, callers or ways in — cut the search short of every way the statement can be
     * @param bool $siteClosed Whether every sibling candidate at this call site has closed dependencies
     */
    public function __construct(
        public readonly string $id,
        public readonly StatementKind $kind,
        public readonly TextPattern $pattern,
        public readonly array $tables,
        public readonly array $placeholders,
        public readonly CallSite $site,
        public readonly array $findings,
        public readonly bool $correlated = true,
        public readonly array $through = [],
        public readonly bool $truncated = false,
        public readonly bool $siteClosed = true,
    ) {
    }

    /**
     * How far the analyzer got with the statement, and why it got no further.
     */
    public function resolution(): Resolution
    {
        return Resolution::of($this->pattern);
    }

    /**
     * Whether the analyzer followed every way the statement can be to its end.
     *
     * A statement whose text reached runtime input is closed: the trail was
     * followed as far as it goes. One cut short by a budget, or by a limit on
     * how many loop passes or callers are followed, is not, however much of
     * its text resolved. An unresolved sibling keeps the entire call site open.
     * Closure never guarantees that a candidate is reachable at runtime.
     */
    public function searchClosed(): bool
    {
        return $this->resolution()->isClosed() && !$this->truncated && $this->siteClosed;
    }

    /**
     * The statement text, with every gap shown as `{$}`.
     */
    public function sql(): string
    {
        return $this->pattern->display();
    }

    /**
     * The statement as the parts it is known in: runs of text and the gaps between them.
     *
     * @return list<StatementPart>
     */
    public function parts(): array
    {
        return StatementPart::of($this->pattern);
    }

    /**
     * The first gap in the statement, or null when there is none.
     */
    public function firstGap(): ?StatementPart
    {
        foreach ($this->parts() as $part) {
            if ($part->isGap) {
                return $part;
            }
        }

        return null;
    }

    /**
     * Whether every character of the statement is known.
     */
    public function isExact(): bool
    {
        return $this->pattern->isExact();
    }

    /**
     * The highest severity among the findings, or `Info` when there are none.
     */
    public function severity(): Severity
    {
        $highest = Severity::Info;
        foreach ($this->findings as $finding) {
            $highest = $finding->severity->atLeast($highest) ? $finding->severity : $highest;
        }

        return $highest;
    }

    /**
     * Whether the statement carries a finding of the given rule.
     */
    public function hasFinding(FindingRule $rule): bool
    {
        foreach ($this->findings as $finding) {
            if ($finding->rule === $rule) {
                return true;
            }
        }

        return false;
    }
}
