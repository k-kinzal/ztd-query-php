<?php

declare(strict_types=1);

namespace Tests\Connection;

use Override;
use PDO;

/**
 * Represents a custom PDO driver over an in-memory test connection.
 */
final class OtherDriverPdo extends PDO
{
    /**
     * Report a custom driver while delegating other native attributes.
     */
    #[Override]
    public function getAttribute(int $attribute): mixed
    {
        return $attribute === PDO::ATTR_DRIVER_NAME ? 'custom' : parent::getAttribute($attribute);
    }
}
