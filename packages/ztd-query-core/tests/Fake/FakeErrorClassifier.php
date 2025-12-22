<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Platform\ErrorClassifier;
use ZtdQuery\Connection\Exception\DatabaseException;

/**
 * Fake ErrorClassifier that classifies errors based on driver error code.
 *
 * Uses a simple convention: driver error codes 1000-1999 are schema errors.
 */
final class FakeErrorClassifier implements ErrorClassifier
{
    public function isUnknownSchemaError(DatabaseException $e): bool
    {
        $code = $e->getDriverErrorCode();

        if ($code === null) {
            return false;
        }

        return $code >= 1000 && $code <= 1999;
    }
}
