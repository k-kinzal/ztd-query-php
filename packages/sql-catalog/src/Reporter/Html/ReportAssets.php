<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

/**
 * The stylesheets and the scripts the pages are read with.
 *
 * The design is document-design's doc-ui, carried as the release the pages
 * were written for — the unmodified stylesheet and script of one version,
 * with the notice that says which and where from — rather than loaded from
 * the network at whatever version is current: a report is read years after it
 * is written, offline as often as not, and has to look then the way it looked
 * when it was checked. The report's own script adds statement search and
 * filtering; a small report stylesheet keeps formatted SQL listings unclipped.
 *
 * The assets are written beside the pages rather than inlined into each of
 * them: a report of a thousand statements is a hundred documents, and a
 * stylesheet repeated a hundred times is a hundred copies to download and one
 * to change.
 *
 * @visibility root
 */
final class ReportAssets
{
    /**
     * The files the report carries, as the name each is written under and the resource it is read from.
     */
    public const FILES = [
        PageShell::DESIGN_STYLE => 'document-design-' . PageShell::DESIGN_VERSION . '.css',
        PageShell::DESIGN_SCRIPT => 'document-design-' . PageShell::DESIGN_VERSION . '.js',
        PageShell::DESIGN_LICENSE => 'document-design-LICENSE.txt',
        PageShell::SCRIPT => 'report.js',
        PageShell::STYLE => 'report.css',
    ];

    /**
     * The files the report carries, keyed by the name each is written under.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $files = [];
        foreach (self::FILES as $name => $resource) {
            $files[$name] = $this->read($resource);
        }

        return $files;
    }

    /**
     * One asset read from the package's resources.
     *
     * A missing asset is answered with nothing rather than with a warning: a
     * report that lost its stylesheet is still a readable report, and a run
     * that stops halfway through writing one is not.
     */
    public function read(string $name): string
    {
        $path = dirname(__DIR__, 3) . '/resources/' . $name;
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }
        $contents = file_get_contents($path);

        return $contents === false ? '' : $contents;
    }
}
