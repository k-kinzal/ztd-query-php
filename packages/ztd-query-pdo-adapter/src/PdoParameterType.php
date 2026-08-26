<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;

/**
 * The pdo parameter type.
 */
final class PdoParameterType
{
    /**
     * Builds value.
     *
     * @return int
     */
    public static function fromValue(mixed $value): int
    {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            is_resource($value) => PDO::PARAM_LOB,
            default => PDO::PARAM_STR,
        };
    }
}
