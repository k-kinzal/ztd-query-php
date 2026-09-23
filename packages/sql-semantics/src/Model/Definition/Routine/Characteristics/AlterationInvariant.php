<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Characteristics;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks MySQL routine identities and the release's named-language operand domain.
 * @visibility SqlSemantics
 */
final class AlterationInvariant
{
    /**
     * Requires one local or database-qualified name, without a PostgreSQL overload signature.
     * @throws InvalidStructure
     */
    public static function target(Origin $origin, QualifiedName $name): void
    {
        if ($origin->dialect !== Dialect::MySql || count($name->parts) > 2 || in_array('', $name->parts, true)) {
            throw new InvalidStructure('A MySQL routine alteration requires one nonempty local or database-qualified name.');
        }
    }

    /**
     * Keeps named languages available only in grammars that declare them.
     * @throws InvalidStructure
     */
    public static function changes(Origin $origin, RoutineAlteration $changes): void
    {
        if ($changes->language !== null && strcasecmp($changes->language, 'SQL') !== 0 && in_array($origin->context?->schema()->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44'], true)) {
            throw new InvalidStructure('Named routine languages require a MySQL 8.1+ grammar.');
        }
    }
}
