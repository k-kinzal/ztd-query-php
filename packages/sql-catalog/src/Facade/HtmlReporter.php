<?php

declare(strict_types=1);

namespace SqlCatalog\Facade;

use SqlCatalog\Reporter\Html\HtmlReporter as Reporter;
use SqlCatalog\Reporter\Html\SqlFormatter;

/**
 * Composes HTML reporting with the package's SQL formatting policies.
 *
 * @visibility root
 */
final class HtmlReporter extends Reporter
{
    /**
     * Uses application formatting when supplied.
     */
    public function __construct(?SqlFormatter $formatter = null)
    {
        parent::__construct($formatter ?? Builtins::sqlFormatter());
    }
}
