<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\EntryIdentity;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Sql\PlaceholderScanner;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Sql\StatementKindReader;
use SqlCatalog\Sql\TableReader;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextPattern;

/**
 * Turns the statements found while walking a file into catalog entries.
 *
 * @visibility root
 */
final class EntryFactory
{
    private StatementKindReader $kinds;

    private TableReader $tables;

    private PlaceholderScanner $placeholders;

    private EntryIdentity $identity;

    /**
     * Wires the factory to the readers that describe a statement.
     */
    public function __construct(
        ?StatementKindReader $kinds = null,
        ?TableReader $tables = null,
        ?PlaceholderScanner $placeholders = null,
        ?EntryIdentity $identity = null,
    ) {
        $this->kinds = $kinds ?? new StatementKindReader();
        $this->tables = $tables ?? new TableReader();
        $this->placeholders = $placeholders ?? new PlaceholderScanner();
        $this->identity = $identity ?? new EntryIdentity();
    }

    /**
     * The catalog entries the records describe, without the ones a better record supersedes.
     *
     * @param list<QueryRecord> $records
     * @return list<CatalogEntry>
     */
    public function build(array $records): array
    {
        $entries = [];
        foreach ($this->groupBySite($this->merge($records)) as $group) {
            $built = [];
            foreach ($group as $record) {
                $built[] = $this->buildOne($record, count($group) > 1);
            }
            foreach ($this->dropSuperseded($built) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * The records with every reading of the same statement folded into one.
     *
     * @param list<QueryRecord> $records
     * @return list<QueryRecord>
     */
    public function merge(array $records): array
    {
        $merged = [];
        foreach ($records as $record) {
            $key = $record->siteKey . "\x1f" . $record->pattern->signature();
            $held = $merged[$key] ?? null;
            if ($held === null) {
                $merged[$key] = $record;
                continue;
            }
            $held->absorb($record);
        }

        return array_values($merged);
    }

    /**
     * The records of each call site, keyed by the site.
     *
     * @param list<QueryRecord> $records
     * @return array<string, list<QueryRecord>>
     */
    public function groupBySite(array $records): array
    {
        $groups = [];
        foreach ($records as $record) {
            $groups[$record->siteKey][] = $record;
        }

        return $groups;
    }

    /**
     * The catalog entry one record describes.
     *
     * @param bool $alternatives Whether the call site produced more than one statement
     */
    public function buildOne(QueryRecord $record, bool $alternatives = false): CatalogEntry
    {
        $pattern = $record->pattern;
        $placeholders = $this->bindPlaceholders($pattern, $record);

        return new CatalogEntry(
            $this->identity->compute($record->site, $pattern),
            $record->kind ?? $this->kinds->read($pattern),
            $pattern,
            $this->tables->read($pattern),
            $placeholders,
            $record->site,
            $this->findings($pattern, $record, $placeholders, $alternatives),
        );
    }

    /**
     * The statement's parameters, each carrying whatever was bound to it.
     *
     * @return list<Placeholder>
     */
    public function bindPlaceholders(TextPattern $pattern, QueryRecord $record): array
    {
        $positional = $record->positional();
        $named = $record->named();
        $index = 0;

        $bound = [];
        foreach ($this->placeholders->scan($pattern) as $reference) {
            $value = $reference->isPositional()
                ? ($positional[$index++] ?? null)
                : ($named[$reference->name ?? ''] ?? $this->numberedValue($reference->name, $positional));
            $bound[] = new Placeholder(
                $reference->token,
                $reference->position,
                $reference->name,
                $value === null ? null : ValueDomain::fromDomain($value),
            );
        }

        return $bound;
    }

    /**
     * The value a numbered parameter such as `$1` binds to.
     *
     * @param list<Domain> $positional
     */
    public function numberedValue(?string $name, array $positional): ?Domain
    {
        if ($name === null || !ctype_digit($name)) {
            return null;
        }

        return $positional[((int) $name) - 1] ?? null;
    }

    /**
     * What is worth reporting about a statement.
     *
     * @param list<Placeholder> $placeholders
     * @param bool $alternatives Whether the call site produced more than one statement
     * @return list<Finding>
     */
    public function findings(
        TextPattern $pattern,
        QueryRecord $record,
        array $placeholders,
        bool $alternatives = false,
    ): array {
        $findings = [];
        $holes = $pattern->holes();

        if ($holes !== [] && $this->kinds->read($pattern) === StatementKind::Unknown) {
            $findings[] = Finding::of(FindingRule::UnresolvedSql, 'The statement text did not resolve far enough to read what it does.');
        }
        if ($holes !== []) {
            $findings[] = Finding::of(
                FindingRule::DynamicSql,
                sprintf('%d value(s) are spliced into the statement text rather than bound.', count($holes)),
            );
        }
        foreach ($holes as $hole) {
            if ($hole->origin === Origin::External) {
                $findings[] = Finding::of(
                    FindingRule::ExternalInput,
                    sprintf('A value from %s reaches the statement text.', $hole->origin->describe()),
                );
                break;
            }
        }

        $mismatch = $alternatives ? null : $this->countMismatch($record, $placeholders);

        return $mismatch === null ? $findings : array_merge($findings, [$mismatch]);
    }

    /**
     * The finding for a statement that binds a different number of values than it takes.
     *
     * The count is only asserted for a call site that resolved to exactly one
     * statement. Where the analyzer had to enumerate alternatives, one of them
     * having a different number of placeholders is the enumeration talking, not
     * the code.
     *
     * @param list<Placeholder> $placeholders
     */
    public function countMismatch(QueryRecord $record, array $placeholders): ?Finding
    {
        if (!$record->isBound() || !$record->pattern->isExact()) {
            return null;
        }
        $expected = count($placeholders);
        $bound = count($record->positional()) + count($record->named());
        if ($expected === $bound) {
            return null;
        }

        return Finding::of(
            FindingRule::PlaceholderCountMismatch,
            sprintf('The statement has %d placeholder(s) but %d value(s) are bound.', $expected, $bound),
        );
    }

    /**
     * The entries of one call site, without the ones a more resolved entry covers.
     *
     * Following a call can reach the same database call with the statement text
     * already resolved. When that happens the unresolved reading of the same
     * call is not a second statement, it is the same one seen with less
     * information, and reporting both would overstate what the code does.
     *
     * @param list<CatalogEntry> $entries
     * @return list<CatalogEntry>
     */
    public function dropSuperseded(array $entries): array
    {
        $kept = [];
        foreach ($entries as $entry) {
            if (!$entry->isExact() && $this->isSupersededAt($entry, $entries)) {
                continue;
            }
            $kept[] = $entry;
        }

        return $kept;
    }

    /**
     * Whether a resolved entry at the same call site already covers this one.
     *
     * @param list<CatalogEntry> $entries
     */
    public function isSupersededAt(CatalogEntry $entry, array $entries): bool
    {
        foreach ($entries as $other) {
            if ($other !== $entry && $other->isExact() && $this->covers($entry->pattern, $other->pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether every resolved run of the shape appears, in order, inside the exact statement.
     */
    public function covers(TextPattern $shape, TextPattern $exact): bool
    {
        $text = $exact->display();
        $offset = 0;
        foreach ($shape->segments as $segment) {
            if ($segment instanceof \SqlCatalog\Text\TextHole) {
                continue;
            }
            $found = strpos($text, $segment->display(), $offset);
            if ($found === false) {
                return false;
            }
            $offset = $found + strlen($segment->display());
        }

        return true;
    }
}
