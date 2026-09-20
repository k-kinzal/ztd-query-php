<?php

declare(strict_types=1);

namespace SqlCatalog\Catalog;

use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\TextPattern;

/**
 * One statement the analyzed application can issue.
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
     * @param bool $correlated Whether the alternatives at this call site are ones the code can reach
     * @param list<string> $through The path taken to this reading, from the body the walk started in, outermost first
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
