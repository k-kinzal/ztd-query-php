<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis;

use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Text\TextPattern;

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
     * Records a statement issued at a call site.
     *
     * @param string $siteKey What tells this call apart from every other, including one on the same line
     * @param bool $combined Whether the statement came from pairing parts that vary independently
     * @param list<string> $through The path taken to this reading, from the body the walk started in, outermost first
     * @param bool $truncated Whether a bound cut the search short of every way the statement can be
     */
    public function record(
        CallSite $site,
        string $siteKey,
        TextPattern $pattern,
        ?StatementKind $kind = null,
        bool $combined = false,
        array $through = [],
        bool $truncated = false,
    ): QueryRecord {
        $record = new QueryRecord($site, $siteKey, $pattern, $kind, $combined, $through, $truncated);
        $this->records[] = $record;

        return $record;
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
