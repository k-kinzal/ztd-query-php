<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

/**
 * A result type in the supported native compound domain.
 *
 * @visibility public
 * @example Reading the semantic kind
 *     \SqlSemantics\Platform\MySql\Ir\Rows\ResultKind::Integer->value // => 'int32'
 */
enum ResultKind: string
{
    case Integer = 'int32';
    case WideInteger = 'int64';
    case Unknown = 'unknown';
    case NullOnly = 'null-only';
}
