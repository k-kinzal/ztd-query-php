<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Identity;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Sequence values are PostgreSQL integer literals of arbitrary precision.
 * @visibility SqlSemantics
 */
final class IdentityInvariant
{
    /**
     * @throws InvalidStructure
     */
    public static function integer(Literal $value): void
    {
        if ($value->type->dialect !== Dialect::PostgreSql || preg_match('/^[+-]?[0-9]+$/D', $value->text) !== 1) {
            throw new InvalidStructure('A sequence attribute requires a PostgreSQL integer literal.');
        }
    }
}
