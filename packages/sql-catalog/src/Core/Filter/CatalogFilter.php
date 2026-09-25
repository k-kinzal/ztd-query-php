<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Filter;

use SqlCatalog\Core\Catalog\Catalog;
use SqlCatalog\Core\Catalog\CatalogEntry;
use SqlCatalog\Core\Catalog\Severity;
use SqlCatalog\Core\Sql\StatementKind;

/**
 * Which of the statements found a report should keep.
 *
 * Every criterion that is left empty keeps everything; the ones that are given
 * must all be satisfied, and each is satisfied by any one of its values.
 *
 * @visibility root
 */
final class CatalogFilter
{
    /**
     * @param list<string> $namespaces Namespace prefixes the enclosing function must start with
     * @param list<string> $functions Function names, as `method`, `Class::method` or a fully qualified name
     * @param list<string> $paths Patterns the reported file path must match
     * @param list<StatementKind> $kinds Statement kinds to keep
     * @param list<string> $tables Table names the statement must name
     * @param list<string> $sinks Database call identifiers to keep
     * @param Severity|null $minimumSeverity The lowest severity to keep
     */
    public function __construct(
        public readonly array $namespaces = [],
        public readonly array $functions = [],
        public readonly array $paths = [],
        public readonly array $kinds = [],
        public readonly array $tables = [],
        public readonly array $sinks = [],
        public readonly ?Severity $minimumSeverity = null,
    ) {
    }

    /**
     * Whether the filter would keep everything it is given.
     */
    public function isEmpty(): bool
    {
        return $this->namespaces === []
            && $this->functions === []
            && $this->paths === []
            && $this->kinds === []
            && $this->tables === []
            && $this->sinks === []
            && $this->minimumSeverity === null;
    }

    /**
     * The catalog holding only the statements this filter keeps.
     */
    public function apply(Catalog $catalog): Catalog
    {
        return $this->isEmpty() ? $catalog : $catalog->filter(fn (CatalogEntry $entry): bool => $this->matches($entry));
    }

    /**
     * Whether the filter keeps one statement.
     */
    public function matches(CatalogEntry $entry): bool
    {
        return $this->matchesNamespace($entry)
            && $this->matchesFunction($entry)
            && $this->matchesPath($entry)
            && $this->matchesKind($entry)
            && $this->matchesTable($entry)
            && $this->matchesSink($entry)
            && $this->matchesSeverity($entry);
    }

    /**
     * Whether the enclosing function sits under one of the wanted namespaces.
     */
    public function matchesNamespace(CatalogEntry $entry): bool
    {
        if ($this->namespaces === []) {
            return true;
        }
        foreach ($this->namespaces as $namespace) {
            $prefix = rtrim($namespace, '\\') . '\\';
            if (stripos(ltrim($entry->site->function, '\\'), ltrim($prefix, '\\')) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the enclosing function is one of the wanted ones.
     */
    public function matchesFunction(CatalogEntry $entry): bool
    {
        if ($this->functions === []) {
            return true;
        }
        $written = ltrim($entry->site->function, '\\');
        $separator = strrpos($written, '::');
        $short = $separator === false ? $written : substr($written, $separator + 2);
        foreach ($this->functions as $wanted) {
            $target = ltrim($wanted, '\\');
            $matched = fnmatch($target, $written, FNM_NOESCAPE | FNM_CASEFOLD);
            if (strcasecmp($written, $target) === 0 || strcasecmp($short, $target) === 0 || $matched) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the reported path matches one of the wanted patterns.
     */
    public function matchesPath(CatalogEntry $entry): bool
    {
        if ($this->paths === []) {
            return true;
        }
        foreach ($this->paths as $pattern) {
            if (
                fnmatch($pattern, $entry->site->file, FNM_NOESCAPE)
                || str_starts_with($entry->site->file, rtrim($pattern, '/') . '/')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the statement is of one of the wanted kinds.
     */
    public function matchesKind(CatalogEntry $entry): bool
    {
        return $this->kinds === [] || in_array($entry->kind, $this->kinds, true);
    }

    /**
     * Whether the statement names one of the wanted tables.
     */
    public function matchesTable(CatalogEntry $entry): bool
    {
        if ($this->tables === []) {
            return true;
        }
        foreach ($entry->tables as $table) {
            foreach ($this->tables as $wanted) {
                if (strcasecmp($table, $wanted) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the statement was found at one of the wanted database calls.
     */
    public function matchesSink(CatalogEntry $entry): bool
    {
        return $this->sinks === [] || in_array($entry->site->sink, $this->sinks, true);
    }

    /**
     * Whether the statement is at least as severe as the filter asks for.
     */
    public function matchesSeverity(CatalogEntry $entry): bool
    {
        return $this->minimumSeverity === null || $entry->severity()->atLeast($this->minimumSeverity);
    }
}
