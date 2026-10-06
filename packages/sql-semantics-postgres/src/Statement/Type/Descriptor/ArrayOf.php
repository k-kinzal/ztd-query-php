<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor;

use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * The array type over an element type.
 *
 * PostgreSQL has one array type per element type; declared sizes and the
 * number of dimensions are documentation and do not make a different type.
 * Source: https://www.postgresql.org/docs/17/arrays.html#ARRAYS-DECLARATION.
 *
 * @visibility public
 * @example Naming an array type
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf(\SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4))->name() // => 'integer[]'
 */
final class ArrayOf implements TypeDescriptor
{
    use Snapshot;

    /**
     * @param TypeDescriptor $element The element type
     */
    public function __construct(public readonly TypeDescriptor $element)
    {
    }

    /**
     * Names the type as the server displays it.
     */
    public function name(): string
    {
        return $this->element->name() . '[]';
    }
}
