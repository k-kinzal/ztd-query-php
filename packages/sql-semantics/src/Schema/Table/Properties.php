<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

/**
 * Classified dialect-specific table properties.
 *
 * @visibility public
 */
interface Properties
{
    /**
     * Identifies the language that owns these table options.
     */
    public function dialect(): \SqlSemantics\Dialect;
}
