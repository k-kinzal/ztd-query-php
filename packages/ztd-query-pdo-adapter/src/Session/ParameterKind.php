<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use PDO;

/**
 * Says which of PDO's parameter kinds a value is to be bound as.
 *
 * PDO::bindValue() takes the kind separately from the value, and a caller who
 * passes parameters to execute() never names one. This reads the kind off the
 * value itself, the way PDO would have read it had the caller named none.
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
final class ParameterKind
{
    /**
     * Return PDO's binding flag for the type reported by gettype().
     */
    public static function fromType(string $type): int
    {
        return match ($type) {
            'NULL' => PDO::PARAM_NULL,
            'boolean' => PDO::PARAM_BOOL,
            'integer' => PDO::PARAM_INT,
            'resource' => PDO::PARAM_LOB,
            default => PDO::PARAM_STR,
        };
    }
}
