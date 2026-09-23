<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Spatial;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates spatial-definition operands without interpreting coordinate-system text.
 * @visibility SqlSemantics
 */
final class SpatialOperands
{
    /**
     * Requires a nonzero unsigned 32-bit SRID and a release with spatial-definition DDL.
     * @throws InvalidStructure
     */
    public static function statement(Origin $origin, int $srid): void
    {
        if ($origin->dialect !== Dialect::MySql || in_array($origin->context?->schema()->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
            throw new InvalidStructure('Spatial-definition DDL requires a MySQL release with spatial reference system definitions.');
        }
        if ($srid < 1 || $srid > 4294967295) {
            throw new InvalidStructure('Spatial-definition DDL requires a nonzero unsigned 32-bit SRID.');
        }
    }

    /**
     * Keeps each supplied metadata value as a MySQL text literal.
     * @throws InvalidStructure
     */
    public static function text(Literal ...$values): void
    {
        foreach ($values as $value) {
            if ($value->type->dialect !== Dialect::MySql || $value->literalKind !== LiteralKind::Text) {
                throw new InvalidStructure('Spatial-definition metadata requires MySQL text literals.');
            }
        }
    }
}
