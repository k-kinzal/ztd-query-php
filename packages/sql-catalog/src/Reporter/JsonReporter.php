<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter;

use Override;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Placeholder;

/**
 * Writes the catalog as JSON, in an order two runs can be compared in.
 *
 * Nothing that changes between runs of the same source, such as a timestamp,
 * is written, so the artifact can be committed and its diff read as the change
 * in the SQL an application issues.
 *
 * @phpstan-type BoundValue array{type: string, values: list<string|int|float|bool|null>, exhaustive: bool, origins: list<string>}
 * @phpstan-type PlaceholderNode array{token: string, position: int, name: string|null, value: BoundValue|null}
 * @phpstan-type FindingNode array{rule: string, severity: string, message: string}
 * @phpstan-type SiteNode array{file: string, line: int, function: string, sink: string}
 * @phpstan-type StatementNode array{id: string, kind: string, sql: string, exact: bool, resolution: string, searchClosed: bool, correlated: bool, tables: list<string>, site: SiteNode, through: list<string>, placeholders: list<PlaceholderNode>, findings: list<FindingNode>}
 * @phpstan-type SummaryNode array{statements: int, resolved: int, undetermined: int, findings: int}
 * @phpstan-type CatalogDocument array{'$schema': string, version: int, summary: SummaryNode, statements: list<StatementNode>, problems: list<array{file: string, message: string}>}
 *
 * @visibility root
 */
final class JsonReporter implements ReporterInterface
{
    /**
     * The format version written into the document.
     */
    public const VERSION = 1;

    /**
     * The name the artifact is written under.
     */
    public const FILE = 'catalog.json';

    /**
     * The name the schema describing the artifact is written under.
     */
    public const SCHEMA_FILE = 'catalog-schema.json';

    /**
     * The name the command line selects this reporter by.
     */
    #[Override]
    public function name(): string
    {
        return 'json';
    }

    /**
     * What the reporter produces.
     */
    #[Override]
    public function description(): string
    {
        return 'a deterministic JSON document, suitable for committing and diffing';
    }

    /**
     * The catalog rendered as one JSON document.
     */
    #[Override]
    public function render(Catalog $catalog): CatalogArtifacts
    {
        $encoded = json_encode($this->toArray($catalog), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new CatalogArtifacts(
            [
                self::FILE => ($encoded === false ? '{}' : $encoded) . "\n",
                self::SCHEMA_FILE => $this->schema(),
            ],
            self::FILE,
        );
    }

    /**
     * The schema the document declares itself against.
     *
     * The schema is written beside the document rather than pointed at over the
     * network, so a catalog that has been committed, copied or archived stays
     * readable without asking anything else for the shape of it.
     */
    public function schema(): string
    {
        $schema = file_get_contents(dirname(__DIR__, 2) . '/resources/' . self::SCHEMA_FILE);

        return $schema === false ? '{}' : $schema;
    }

    /**
     * The document the catalog renders to.
     *
     * @return CatalogDocument
     */
    public function toArray(Catalog $catalog): array
    {
        $statements = [];
        foreach ($catalog->sorted() as $entry) {
            $statements[] = $this->entryToArray($entry);
        }
        $problems = [];
        foreach ($catalog->sorted()->problems() as $problem) {
            $problems[] = ['file' => $problem->file, 'message' => $problem->message];
        }

        return [
            '$schema' => self::SCHEMA_FILE,
            'version' => self::VERSION,
            'summary' => $this->summary($catalog),
            'statements' => $statements,
            'problems' => $problems,
        ];
    }

    /**
     * The counts a reader looks at first.
     *
     * @return SummaryNode
     */
    public function summary(Catalog $catalog): array
    {
        $resolved = 0;
        $undetermined = 0;
        $findings = 0;
        foreach ($catalog as $entry) {
            $resolved += $entry->isExact() ? 1 : 0;
            $undetermined += $entry->isExact() ? 0 : 1;
            $findings += count($entry->findings);
        }

        return [
            'statements' => $catalog->count(),
            'resolved' => $resolved,
            'undetermined' => $undetermined,
            'findings' => $findings,
        ];
    }

    /**
     * One statement rendered as a document node.
     *
     * @return StatementNode
     */
    public function entryToArray(CatalogEntry $entry): array
    {
        $findings = [];
        foreach ($entry->findings as $finding) {
            $findings[] = [
                'rule' => $finding->rule->value,
                'severity' => $finding->severity->value,
                'message' => $finding->message,
            ];
        }
        $placeholders = [];
        foreach ($entry->placeholders as $placeholder) {
            $placeholders[] = $this->placeholderToArray($placeholder);
        }

        return [
            'id' => $entry->id,
            'kind' => $entry->kind->value,
            'sql' => $entry->sql(),
            'exact' => $entry->isExact(),
            'resolution' => $entry->resolution()->value,
            'searchClosed' => $entry->searchClosed(),
            'correlated' => $entry->correlated,
            'tables' => $entry->tables,
            'site' => [
                'file' => $entry->site->file,
                'line' => $entry->site->line,
                'function' => $entry->site->function,
                'sink' => $entry->site->sink,
            ],
            'through' => $entry->through,
            'placeholders' => $placeholders,
            'findings' => $findings,
        ];
    }

    /**
     * One bind parameter rendered as a document node.
     *
     * @return PlaceholderNode
     */
    public function placeholderToArray(Placeholder $placeholder): array
    {
        $value = $placeholder->value;

        return [
            'token' => $placeholder->token,
            'position' => $placeholder->position,
            'name' => $placeholder->name,
            'value' => $value === null ? null : [
                'type' => $value->type,
                'values' => $value->values,
                'exhaustive' => $value->exhaustive,
                'origins' => $value->origins,
            ],
        ];
    }
}
