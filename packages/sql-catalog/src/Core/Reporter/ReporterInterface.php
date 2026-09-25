<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Reporter;

use SqlCatalog\Core\Catalog\Catalog;

/**
 * One way of writing a catalog out.
 *
 * A reporter only renders; where the result goes is decided by whoever asked
 * for it, which is what keeps a machine-readable artifact and a human-readable
 * one interchangeable.
 *
 * @visibility root
 */
interface ReporterInterface
{
    /**
     * The name the command line selects the reporter by.
     */
    public function name(): string;

    /**
     * What the reporter produces, shown in the command line help.
     */
    public function description(): string;

    /**
     * The files the catalog renders to.
     */
    public function render(Catalog $catalog): CatalogArtifacts;
}
