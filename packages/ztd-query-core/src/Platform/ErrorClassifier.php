<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Connection\Exception\DatabaseException;

/**
 * Interface for classifying database errors by platform.
 *
 * This allows platform-specific error classification logic to be
 * abstracted away from the core ZTD layer.
 */
interface ErrorClassifier
{
    /**
     * Check if the exception represents an unknown schema error.
     *
     * Unknown schema errors include:
     * - Unknown column references
     * - Non-existent table references
     * - Undeclared variables
     */
    public function isUnknownSchemaError(DatabaseException $e): bool;
}
