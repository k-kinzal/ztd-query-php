<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

/**
 * The known NULL value before an enclosing operation supplies a concrete data type.
 * @visibility public
 * @example Distinguishing SQL NULL from a missing declaration
 *     \SqlSemantics\Statement\Type\NullDomain::Null->name // => 'Null'
 */
enum NullDomain
{
    case Null;
}
