<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Invocation;

use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\IntervalSpan;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeDescriptor;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The implicit casts between `pg_catalog` types that function resolution may apply.
 *
 * Rule: PG-IMPLICIT-CAST-001. The table lists the casts of `pg_cast` marked
 * implicit among the numeric, string, date/time and bit-string types; a
 * value of type `unknown` (a string constant or NULL) can be converted to any
 * type. Types are compared without modifiers: a modifier never selects a
 * function. Source: https://www.postgresql.org/docs/17/typeconv-func.html,
 * https://www.postgresql.org/docs/17/sql-createcast.html. Termination: constant work. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Coercions
{
    /**
     * The implicit cast targets of each source type.
     */
    private const IMPLICIT = [
        'int2' => ['int4', 'int8', 'numeric', 'float4', 'float8', 'oid'],
        'int4' => ['int8', 'numeric', 'float4', 'float8', 'oid'],
        'int8' => ['numeric', 'float4', 'float8', 'oid'],
        'numeric' => ['float4', 'float8'],
        'float4' => ['float8'],
        'varchar' => ['text', 'bpchar', 'name'],
        'bpchar' => ['text', 'varchar', 'name'],
        'name' => ['text'],
        'text' => ['varchar', 'bpchar', 'name'],
        'date' => ['timestamp', 'timestamptz'],
        'timestamp' => ['timestamptz'],
        'time' => ['timetz', 'interval'],
        'bit' => ['varbit'],
        'varbit' => ['bit'],
    ];

    /**
     * Answers the type name an argument contributes to function lookup: `unknown` for a string constant or NULL, null when the type is not a catalog type of the table.
     */
    public function key(TypeFact $fact): ?string
    {
        if ($fact instanceof NullOnly) {
            return Builtin::Unknown->value;
        }

        return $fact instanceof Known ? $this->descriptor($fact->descriptor) : null;
    }

    /**
     * Answers the catalog name of a type without its modifiers, null for a type the table does not name.
     */
    public function descriptor(TypeDescriptor $descriptor): ?string
    {
        if ($descriptor instanceof Builtin) {
            return $descriptor->value;
        }
        if ($descriptor instanceof Parameterized) {
            return $descriptor->base->value;
        }
        if ($descriptor instanceof IntervalSpan) {
            return Builtin::Interval->value;
        }
        if ($descriptor instanceof ArrayOf) {
            $element = $this->descriptor($descriptor->element);

            return $element === null ? null : $element . '[]';
        }

        return null;
    }

    /**
     * Tells whether a value of one type can be passed where another is expected without an explicit cast.
     */
    public function implicit(string $from, string $to): bool
    {
        return $from === $to || $from === Builtin::Unknown->value || $to === 'any' || in_array($to, self::IMPLICIT[$from] ?? [], true);
    }

    /**
     * Answers the type a catalog name of the table denotes.
     */
    public function type(string $name): TypeDescriptor
    {
        return str_ends_with($name, '[]') ? new ArrayOf($this->type(substr($name, 0, -2))) : Builtin::from($name);
    }
}
