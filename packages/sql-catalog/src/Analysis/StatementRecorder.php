<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\TextPattern;

/**
 * Collects the statements found while walking one file.
 *
 * A prepared statement is filed under the call site that prepared it, so the
 * `execute()` that comes later can find the records it binds values to even
 * when the prepare produced several alternative statements.
 *
 * @visibility root
 */
final class StatementRecorder
{
    /**
     * @var list<QueryRecord>
     */
    private array $records = [];

    /**
     * @var array<string, list<QueryRecord>>
     */
    private array $prepared = [];

    /**
     * @var array<string, true>
     */
    private array $visited = [];

    /**
     * @var array<string, true>
     */
    private array $explained = [];

    /**
     * Records a statement issued at a call site.
     *
     * @param string $siteKey What tells this call apart from every other, including one on the same line
     * @param bool $combined Whether the statement came from pairing parts that vary independently
     * @param list<string> $through The path taken to this reading, from the body the walk started in, outermost first
     */
    public function record(
        CallSite $site,
        string $siteKey,
        TextPattern $pattern,
        ?StatementKind $kind = null,
        bool $combined = false,
        array $through = [],
    ): QueryRecord {
        $record = new QueryRecord($site, $siteKey, $pattern, $kind, $combined, $through);
        $this->records[] = $record;

        return $record;
    }

    /**
     * Notes that the walk reached a call, whether or not it turned out to be a database call.
     */
    public function markVisited(string $siteKey): void
    {
        $this->visited[$siteKey] = true;
    }

    /**
     * Whether the walk reached a call.
     */
    public function hasVisited(string $siteKey): bool
    {
        return isset($this->visited[$siteKey]);
    }

    /**
     * Notes that the walk established what a call is.
     *
     * A call is established either by matching a database call or by being
     * made on something the walk could name: a call on a known class that is
     * not a database handle is not a database call, and saying so is different
     * from not having been able to tell.
     */
    public function markExplained(string $siteKey): void
    {
        $this->explained[$siteKey] = true;
    }

    /**
     * Whether the walk established what a call is.
     */
    public function hasExplained(string $siteKey): bool
    {
        return isset($this->explained[$siteKey]);
    }

    /**
     * Records a statement that was prepared, filed under the handle it produced.
     *
     * @param list<QueryRecord> $records
     */
    public function filePrepared(string $handle, array $records): void
    {
        $this->prepared[$handle] = $records;
    }

    /**
     * The statements a handle stands for.
     *
     * @return list<QueryRecord>
     */
    public function prepared(string $handle): array
    {
        return $this->prepared[$handle] ?? [];
    }

    /**
     * Every statement recorded so far.
     *
     * @return list<QueryRecord>
     */
    public function records(): array
    {
        return $this->records;
    }
}
