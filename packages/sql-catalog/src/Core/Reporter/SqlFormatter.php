<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Reporter;

/**
 * An optional SQL presentation policy supplied by an application.
 *
 * @visibility root
 */
interface SqlFormatter
{
    /**
     * Formats accepted syntax, or returns null when this policy cannot format it.
     */
    public function format(string $sql): ?string;
}
