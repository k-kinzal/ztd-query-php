<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter;

use Override;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;

/**
 * Writes the catalog as plain lines, for reading in a terminal.
 *
 * @visibility root
 */
final class TextReporter implements ReporterInterface
{
    /**
     * The name the artifact is written under.
     */
    public const FILE = 'catalog.txt';

    /**
     * The name the command line selects this reporter by.
     */
    #[Override]
    public function name(): string
    {
        return 'text';
    }

    /**
     * What the reporter produces.
     */
    #[Override]
    public function description(): string
    {
        return 'one block per statement, for reading in a terminal';
    }

    /**
     * The catalog rendered as plain text.
     */
    #[Override]
    public function render(Catalog $catalog): CatalogArtifacts
    {
        $lines = [];
        foreach ($catalog->sorted() as $entry) {
            foreach ($this->entryLines($entry) as $line) {
                $lines[] = $line;
            }
            $lines[] = '';
        }
        foreach ($catalog->sorted()->problems() as $problem) {
            $lines[] = sprintf('! %s: %s', $problem->file, $problem->message);
        }
        $lines[] = $this->summaryLine($catalog);

        return CatalogArtifacts::one(self::FILE, implode("\n", $lines) . "\n");
    }

    /**
     * The lines one statement is written as.
     *
     * @return list<string>
     */
    public function entryLines(CatalogEntry $entry): array
    {
        $lines = [
            sprintf('%s  %s  %s', $entry->site->display(), strtoupper($entry->kind->value), $entry->id),
            sprintf('  in %s via %s', $entry->site->function, $entry->site->sink),
            '  ' . $entry->sql(),
            '  ' . $this->statusLine($entry),
        ];
        foreach ($entry->placeholders as $placeholder) {
            $lines[] = sprintf('  %s = %s', $placeholder->token, $placeholder->value?->display() ?? '(unbound)');
        }
        foreach ($entry->findings as $finding) {
            $lines[] = sprintf('  [%s] %s %s', strtoupper($finding->severity->value), $finding->rule->value, $finding->message);
        }

        return $lines;
    }

    /**
     * The line saying how far the analyzer got with a statement.
     */
    public function statusLine(CatalogEntry $entry): string
    {
        $status = [$entry->resolution()->value];
        if (!$entry->searchClosed()) {
            $status[] = 'search did not close';
        }
        if (!$entry->correlated) {
            $status[] = 'alternatives may be unreachable';
        }
        if (count($entry->through) > 1) {
            $status[] = 'via ' . implode(' -> ', $entry->through);
        }

        return implode('; ', $status);
    }

    /**
     * The closing line naming what the run found.
     */
    public function summaryLine(Catalog $catalog): string
    {
        $exact = 0;
        $findings = 0;
        foreach ($catalog as $entry) {
            $exact += $entry->isExact() ? 1 : 0;
            $findings += count($entry->findings);
        }

        return sprintf(
            '%d statement(s), %d fully resolved, %d finding(s), %d unreadable file(s).',
            $catalog->count(),
            $exact,
            $findings,
            count($catalog->problems()),
        );
    }
}
