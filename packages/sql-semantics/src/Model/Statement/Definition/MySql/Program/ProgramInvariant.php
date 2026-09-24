<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Program;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Definition\Routine\Stored\ExternalRoutineCode;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramStructure;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the MySQL dialect, object names, release-specific forms and body structure of stored program definitions.
 * @visibility SqlSemantics
 */
final class ProgramInvariant
{
    /**
     * Requires MySQL, a local or database-qualified nonempty name, and IF NOT EXISTS only where the release allows it.
     * @throws InvalidStructure
     */
    public static function definition(Origin $origin, QualifiedName $name, bool $ifNotExists, ProgramKind $kind): void
    {
        if ($origin->dialect !== Dialect::MySql || count($name->parts) > 2 || in_array('', $name->parts, true)) {
            throw new InvalidStructure('A MySQL stored program requires one nonempty local or database-qualified name.');
        }
        if ($ifNotExists && $kind !== ProgramKind::Event && self::before($origin, 'mysql-8.0.44')) {
            throw new InvalidStructure('IF NOT EXISTS for routines and triggers requires a MySQL 8.0 grammar.');
        }
    }

    /**
     * Requires a string body only for named languages available from MySQL 8.1, and validates a compound-statement body.
     * @throws InvalidStructure
     */
    public static function body(Origin $origin, ProgramStatement|ExternalRoutineCode $body, ProgramKind $kind): void
    {
        if ($body instanceof ExternalRoutineCode) {
            if (self::before($origin, 'mysql-8.1.0')) {
                throw new InvalidStructure('A routine body in another language requires a MySQL 8.1+ grammar.');
            }
            return;
        }
        ProgramStructure::check($body, $kind);
    }

    /**
     * Requires names that are nonempty and unique ignoring case.
     * @param list<string> $names
     * @throws InvalidStructure
     */
    public static function distinct(array $names): void
    {
        $folded = array_map(strtolower(...), $names);
        if (in_array('', $names, true) || count(array_unique($folded)) !== count($folded)) {
            throw new InvalidStructure('Stored routine parameters have distinct names.');
        }
    }

    /**
     * Reports whether the snapshot's grammar release precedes the named release; an unknown release precedes none.
     */
    public static function before(Origin $origin, string $release): bool
    {
        $order = ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'];
        $current = array_search($origin->context?->schema()->grammarVersion, $order, true);
        $limit = array_search($release, $order, true);
        return $current !== false && $limit !== false && $current < $limit;
    }
}
