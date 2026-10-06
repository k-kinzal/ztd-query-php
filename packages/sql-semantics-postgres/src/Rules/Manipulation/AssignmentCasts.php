<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Categories;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Checks that a value assigned to a column converts to the column's type in assignment context.
 *
 * Rule: PG-ASSIGNMENT-CAST-001. A value converts when its type is the
 * column type, converts implicitly (PG-TYPE-CATEGORY-001), when both are
 * numeric types, when the column is of a string type (automatic I/O
 * conversion to string types is an assignment cast), or along one of the
 * listed `pg_cast` entries of context `a`. A string constant or NULL
 * (pseudo-type `unknown`) converts to any type. Only a pair of types that
 * both belong to the covered catalog types and that none of these allow is
 * reported; any other pair (domains, arrays, user types, `money`, `oid`,
 * `xml`, a value whose type depends on missing declarations) is accepted.
 * Termination: constant work.
 * Source: https://www.postgresql.org/docs/17/typeconv-query.html, https://www.postgresql.org/docs/17/sql-createcast.html,
 * https://www.postgresql.org/docs/17/catalog-pg-cast.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class AssignmentCasts
{
    /**
     * The catalog types whose assignment casts the rule lists completely.
     */
    private const COVERED = [
        'int2', 'int4', 'int8', 'numeric', 'float4', 'float8', 'bool', 'text', 'varchar', 'bpchar', 'name',
        'date', 'time', 'timetz', 'timestamp', 'timestamptz', 'interval', 'bytea', 'uuid', 'json', 'jsonb', 'inet', 'cidr', 'bit', 'varbit',
    ];

    /**
     * The assignment casts between covered types that are not implicit, by source type.
     */
    private const ASSIGNMENT = [
        'timestamp' => ['date', 'time'],
        'timestamptz' => ['date', 'time', 'timestamp', 'timetz'],
        'interval' => ['time'],
        'timetz' => ['time'],
        'json' => ['jsonb'],
        'jsonb' => ['json'],
        'inet' => ['cidr'],
    ];

    /**
     * Tells whether a value of one catalog type is certainly not assignable to a column of another.
     */
    public function refused(Builtin $value, Builtin $column): bool
    {
        $categories = new Categories();
        if (!in_array($value->value, self::COVERED, true) || !in_array($column->value, self::COVERED, true)) {
            return false;
        }

        return !$categories->implicit($value, $column)
            && !($categories->numeric($value) && $categories->numeric($column))
            && !$categories->textual($column)
            && !in_array($column->value, self::ASSIGNMENT[$value->value] ?? [], true);
    }

    /**
     * Reports a value that the column it is assigned to cannot take.
     */
    public function check(OutputSlot $column, TypeFact $value, Derivation $derivation): void
    {
        $categories = new Categories();
        $from = $categories->builtin($value);
        $to = $categories->builtin($column->type);
        if ($from === null || $to === null || $from === Builtin::Unknown || !$value instanceof Known || !$column->type instanceof Known || !$this->refused($from, $to)) {
            return;
        }
        $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::AssignmentType, $column->name->value ?? '', $column->type->descriptor->name(), $value->descriptor->name()));
    }
}
