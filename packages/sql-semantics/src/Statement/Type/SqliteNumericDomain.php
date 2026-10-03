<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

use SqlSemantics\Statement\Declaration\Builtin;

/**
 * A known numeric result whose integer or real storage class depends on runtime values.
 * @visibility public
 * @example Inspecting the two possible numeric storage classes
 *     \SqlSemantics\Statement\Type\SqliteNumericDomain::IntegerOrReal->storageClasses() // => [\SqlSemantics\Statement\Declaration\Builtin::Integer, \SqlSemantics\Statement\Declaration\Builtin::DoublePrecision]
 */
enum SqliteNumericDomain
{
    case IntegerOrReal;

    /**
     * Integer overflow can promote a result to binary64 even when its inputs are integers.
     * @return non-empty-list<Builtin>
     */
    public function storageClasses(): array
    {
        return [Builtin::Integer, Builtin::DoublePrecision];
    }
}
