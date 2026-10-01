<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

/**
 * A semantic contradiction preventing a reference from having a result type.
 * @visibility public
 * @example Distinguishing ambiguity from missing type information
 *     \SqlSemantics\Statement\Type\Invalid::AmbiguousColumn->name // => 'AmbiguousColumn'
 */
enum Invalid
{
    case MissingColumn;
    case AmbiguousColumn;
    case AmbiguousTable;
}
