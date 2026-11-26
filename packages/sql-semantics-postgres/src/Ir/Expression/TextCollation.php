<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

/**
 * A resolved builtin collation for native UTF-8 text.
 *
 * @visibility public
 * @example Naming the builtin byte ordering
 *     \SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation::C->value // => 'C'
 */
enum TextCollation: string
{
    case C = 'C';
}
