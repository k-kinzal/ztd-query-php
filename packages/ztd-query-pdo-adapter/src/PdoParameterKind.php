<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;

/**
 * Says which of PDO's parameter kinds a value is to be bound as.
 *
 * PDO::bindValue() takes the kind separately from the value, and a caller who
 * passes parameters to execute() never names one. This reads the kind off the
 * value itself, the way PDO would have read it had the caller named none.
 */
final class PdoParameterKind
{
    /**
     * Answers the PDO parameter kind a value is bound as.
     *
     * @param mixed $value Value about to be bound
     *
     * @return int One of PDO::PARAM_*
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
