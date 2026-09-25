<?php

declare(strict_types=1);

namespace SqlCatalog\Facade;

use SqlCatalog\Core\Reporter\ReporterRegistry as Registry;
use SqlCatalog\Reporter\Json\JsonReporter;
use SqlCatalog\Reporter\Text\TextReporter;

/**
 * Composition of built-in reporters for the command and legacy API.
 *
 * @visibility root
 */
final class ReporterRegistry extends Registry
{
    /**
     * The independent reporters shipped with the package.
     */
    public static function withBuiltins(): self
    {
        return new self([new JsonReporter(), new HtmlReporter(), new TextReporter()]);
    }
}
