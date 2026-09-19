<?php

declare(strict_types=1);

namespace SqlCatalog\Source;

/**
 * One PHP file to analyze, named the way the catalog reports it.
 *
 * @visibility root
 */
final class SourceFile
{
    /**
     * @param string $path The path the catalog reports, relative to the analysis root
     * @param string $code The source text
     */
    public function __construct(
        public readonly string $path,
        public readonly string $code,
    ) {
    }
}
