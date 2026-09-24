<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Replication;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the start position of a log event listing.
 * @visibility SqlSemantics
 */
final class LogEventPosition
{
    /**
     * A position is a MySQL numeric literal written as given.
     * @throws InvalidStructure
     */
    public static function check(?Literal $position): void
    {
        if ($position !== null && ($position->literalKind !== LiteralKind::Number || $position->type->dialect !== Dialect::MySql)) {
            throw new InvalidStructure('A log event position is a MySQL numeric literal.');
        }
    }
}
