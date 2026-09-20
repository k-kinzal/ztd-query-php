<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

/**
 * The stylesheet and the script the pages are read with.
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
     * The files the report carries, keyed by the name each is written under.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return [
            PageShell::STYLE => $this->read('report.css'),
            PageShell::SCRIPT => $this->read('report.js'),
        ];
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
