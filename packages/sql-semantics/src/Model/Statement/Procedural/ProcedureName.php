<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Procedural;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates a MySQL stored-procedure name: an optional database and a routine name the server accepts.
 * @visibility SqlSemantics
 */
final class ProcedureName
{
    /**
     * Each part is nonempty, at most 64 characters and does not end with a space.
     * @throws InvalidStructure
     */
    public static function validate(QualifiedName $name): void
    {
        if (count($name->parts) > 2) {
            throw new InvalidStructure('A procedure name has at most a database and a routine part.');
        }
        foreach ($name->parts as $part) {
            if ($part === '' || str_ends_with($part, ' ') || mb_strlen($part) > 64) {
                throw new InvalidStructure('A procedure name part must be nonempty, at most 64 characters and not end with a space.');
            }
        }
    }
}
