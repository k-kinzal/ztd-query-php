<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Schema;

/**
 * How a database supplies a generated column value.
 * @visibility public
 * @example Stored generation
 *     \SqlSemantics\Core\Schema\GenerationKind::Stored->value // => 'stored'
 */
enum GenerationKind: string
{
    case Virtual = 'virtual';
    case Stored = 'stored';
    case Identity = 'identity';
}
