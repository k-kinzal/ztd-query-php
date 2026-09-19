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
     * Records a statement issued at a call site.
     *
     * @param string $siteKey What tells this call apart from every other, including one on the same line
     */
    public function record(CallSite $site, string $siteKey, TextPattern $pattern, ?StatementKind $kind = null): QueryRecord
    {
        $record = new QueryRecord($site, $siteKey, $pattern, $kind);
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
