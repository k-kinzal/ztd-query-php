<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

/**
 * A result type in the supported native compound domain.
 *
 * @visibility public
 * @example Reading the semantic kind
 *     \SqlSemantics\Platform\PostgreSql\Ir\Rows\ResultKind::Integer->value // => 'int32'
 */
enum ResultKind: string
{
    case Integer = 'int32';
    case WideInteger = 'int64';
    case Boolean = 'bool';
    case Unknown = 'unknown';
    case Text = 'text';
}
