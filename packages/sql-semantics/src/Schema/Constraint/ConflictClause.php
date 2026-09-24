<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Constraint;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Policy\ConstraintResponse;

/**
 * Checks a declared ON CONFLICT resolution: only SQLite constraints declare one.
 *
 * @visibility SqlSemantics
 */
final class ConflictClause
{
    /**
     * Rejects a resolution declared outside SQLite; Default means no ON CONFLICT clause.
     * @throws InvalidStructure
     */
    public static function check(ConstraintResponse $resolution, Dialect $dialect): void
    {
        if ($resolution !== ConstraintResponse::Default && $dialect !== Dialect::Sqlite) {
            throw new InvalidStructure('An ON CONFLICT constraint resolution requires SQLite.');
        }
    }
}
