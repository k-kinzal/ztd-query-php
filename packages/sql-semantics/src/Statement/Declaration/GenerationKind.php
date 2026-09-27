<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

/**
 * How a database supplies a generated column value.
 * @visibility public
 * @example Stored generation
 *     \SqlSemantics\Statement\Declaration\GenerationKind::Stored->value // => 'stored'
 */
enum GenerationKind: string
{
    case Virtual = 'virtual';
    case Stored = 'stored';
    case Identity = 'identity';
}
