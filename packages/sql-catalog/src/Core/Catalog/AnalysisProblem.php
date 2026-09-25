<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Catalog;

/**
 * A file the analyzer could not read, reported alongside the catalog.
 *
 * @visibility root
 */
final class AnalysisProblem
{
    /**
     * @param string $file The file that could not be analyzed, relative to the analysis root
     * @param string $message Why it could not be analyzed
     */
    public function __construct(
        public readonly string $file,
        public readonly string $message,
    ) {
    }
}
