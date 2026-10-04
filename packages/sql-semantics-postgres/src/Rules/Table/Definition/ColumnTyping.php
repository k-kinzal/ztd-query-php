<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * Answers the type a column definition declares.
 *
 * Rule: PG-COLUMN-TYPE-001. A column has the type its type name denotes
 * (PG-TYPE-LOOKUP-001). The serial pseudo-types are expanded first, as
 * `transformColumnDefinition` does for a one-part type name without array
 * bounds: `smallserial`/`serial2` is `int2`, `serial`/`serial4` is `int4`,
 * `bigserial`/`serial8` is `int8`, and the column is NOT NULL with a
 * sequence default. An array of a serial type is an error. A type the
 * context cannot know has no descriptor, and the declaration stops there.
 * Source: https://www.postgresql.org/docs/17/datatype-numeric.html#DATATYPE-SERIAL.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ColumnTyping
{
    /**
     * The serial pseudo-types and the integer types they stand for.
     */
    private const SERIAL = [
        'smallserial' => Builtin::Int2, 'serial2' => Builtin::Int2,
        'serial' => Builtin::Int4, 'serial4' => Builtin::Int4,
        'bigserial' => Builtin::Int8, 'serial8' => Builtin::Int8,
    ];

    /**
     * Answers the integer type a serial type name stands for, or null when the name is not a serial type.
     */
    public function serial(TypeName $type): ?Builtin
    {
        $designation = $type->designation;
        if (!$designation instanceof NamedDesignation || count($designation->name->parts) !== 1 || $type->setOf) {
            return null;
        }

        return self::SERIAL[$designation->name->parts[0]->value] ?? null;
    }

    /**
     * Answers the declared type of a column, or null when the context cannot know it.
     */
    public function descriptor(TypeName $type, AnalysisContext $context): ?TypeDescriptor
    {
        $serial = $this->serial($type);
        if ($serial !== null) {
            return $type->array === null ? $serial : null;
        }
        $fact = $type->typeFact($context);

        return $fact instanceof Known ? $fact->descriptor : null;
    }
}
