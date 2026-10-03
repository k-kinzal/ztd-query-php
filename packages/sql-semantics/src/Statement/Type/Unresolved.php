<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

/**
 * A missing input fact that prevents type determination, never an unimplemented construct.
 * @visibility public
 * @example Identifying why a column type is unknown
 *     \SqlSemantics\Statement\Type\Unresolved::MissingDeclaration->name // => 'MissingDeclaration'
 */
enum Unresolved
{
    case MissingDeclaration;
}
