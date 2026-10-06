<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing;

use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\IntervalSpan;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The type categories, preferred types and implicit casts of the `pg_catalog` types that implicit conversion uses.
 *
 * Rule: PG-TYPE-CATEGORY-001. The categories are those of `pg_type.typcategory`
 * (B boolean, D date/time, G geometric, I network, N numeric, S string, T
 * timespan, U user-defined, V bit string, A array, P pseudo); the preferred
 * types are those with `typispreferred`; the implicit casts are the
 * `pg_cast` entries of context `i` between the listed types. Termination:
 * constant work. Source: https://www.postgresql.org/docs/17/catalog-pg-type.html#CATALOG-TYPCATEGORY-TABLE,
 * https://www.postgresql.org/docs/17/typeconv-overview.html, https://www.postgresql.org/docs/17/catalog-pg-cast.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Categories
{
    /**
     * The category of each catalog type; a type not listed is user-defined (U).
     */
    private const CATEGORY = [
        'bool' => 'B', 'int2' => 'N', 'int4' => 'N', 'int8' => 'N', 'numeric' => 'N', 'float4' => 'N', 'float8' => 'N', 'oid' => 'N', 'money' => 'N',
        'text' => 'S', 'varchar' => 'S', 'bpchar' => 'S', 'name' => 'S', 'char' => 'Z',
        'date' => 'D', 'time' => 'D', 'timetz' => 'D', 'timestamp' => 'D', 'timestamptz' => 'D', 'interval' => 'T',
        'bit' => 'V', 'varbit' => 'V', 'inet' => 'I', 'cidr' => 'I',
        'point' => 'G', 'line' => 'G', 'lseg' => 'G', 'box' => 'G', 'path' => 'G', 'polygon' => 'G', 'circle' => 'G',
        'int4range' => 'R', 'int8range' => 'R', 'numrange' => 'R', 'tsrange' => 'R', 'tstzrange' => 'R', 'daterange' => 'R',
        'record' => 'P', 'void' => 'P', 'cstring' => 'P', 'unknown' => 'X',
    ];

    /**
     * The preferred type of each category that has one.
     */
    private const PREFERRED = ['bool', 'float8', 'oid', 'text', 'timestamptz', 'interval', 'varbit', 'inet'];

    /**
     * The implicit casts between catalog types, by source type.
     */
    private const IMPLICIT = [
        'int2' => ['int4', 'int8', 'numeric', 'float4', 'float8', 'oid'],
        'int4' => ['int8', 'numeric', 'float4', 'float8', 'oid'],
        'int8' => ['numeric', 'float4', 'float8', 'oid'],
        'numeric' => ['float4', 'float8'],
        'float4' => ['float8'],
        'bpchar' => ['text', 'varchar', 'name'],
        'varchar' => ['text', 'bpchar', 'name'],
        'text' => ['bpchar', 'varchar', 'name'],
        'name' => ['text', 'bpchar', 'varchar'],
        'char' => ['text'],
        'date' => ['timestamp', 'timestamptz'],
        'time' => ['interval', 'timetz'],
        'timestamp' => ['timestamptz'],
        'bit' => ['varbit'],
        'varbit' => ['bit'],
        'cidr' => ['inet'],
    ];

    /**
     * Answers the catalog type a known type fact contributes to implicit conversion; `unknown` for NULL; null for a type these tables do not cover.
     */
    public function builtin(TypeFact $type): ?Builtin
    {
        if ($type instanceof NullOnly) {
            return Builtin::Unknown;
        }
        if (!$type instanceof Known) {
            return null;
        }
        if ($type->descriptor instanceof Parameterized) {
            return $type->descriptor->base;
        }
        if ($type->descriptor instanceof IntervalSpan) {
            return Builtin::Interval;
        }

        return $type->descriptor instanceof Builtin ? $type->descriptor : null;
    }

    /**
     * Answers the category letter of a catalog type.
     */
    public function category(Builtin $type): string
    {
        return self::CATEGORY[$type->value] ?? 'U';
    }

    /**
     * Tells whether a catalog type is the preferred type of its category.
     */
    public function preferred(Builtin $type): bool
    {
        return in_array($type->value, self::PREFERRED, true);
    }

    /**
     * Tells whether a value of one catalog type converts to another implicitly, the same type included.
     */
    public function implicit(Builtin $from, Builtin $to): bool
    {
        return $from === $to || in_array($to->value, self::IMPLICIT[$from->value] ?? [], true);
    }

    /**
     * Tells whether a catalog type is numeric: one of the integer, numeric and floating-point types.
     */
    public function numeric(Builtin $type): bool
    {
        return in_array($type, [Builtin::Int2, Builtin::Int4, Builtin::Int8, Builtin::Numeric, Builtin::Float4, Builtin::Float8], true);
    }

    /**
     * Tells whether a catalog type is a character string type.
     */
    public function textual(Builtin $type): bool
    {
        return in_array($type, [Builtin::Text, Builtin::Varchar, Builtin::Bpchar, Builtin::Name], true);
    }
}
