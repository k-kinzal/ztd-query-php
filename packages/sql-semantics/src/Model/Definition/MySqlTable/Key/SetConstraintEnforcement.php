<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Key;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Enforces or stops enforcing a CHECK constraint, addressed as CHECK or as CONSTRAINT (MySQL 8.0 and later).
 * @visibility public
 * @example Suspending a check
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ALTER CHECK positive NOT ENFORCED');
 *     [$statement->alterations[0]->name, $statement->alterations[0]->enforced] // => ['positive', false]
 * @example Rejecting an index address
 *     new \SqlSemantics\Model\Definition\MySqlTable\Key\SetConstraintEnforcement('c', \SqlSemantics\Model\Definition\MySqlTable\Key\KeyKind::Index, true); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetConstraintEnforcement implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly KeyKind $kind, public readonly bool $enforced)
    {
        AlterationInvariant::name($name);
        if (!in_array($kind, [KeyKind::Check, KeyKind::Constraint], true)) {
            throw new InvalidStructure('Constraint enforcement addresses a CHECK or a CONSTRAINT.');
        }
    }
}
