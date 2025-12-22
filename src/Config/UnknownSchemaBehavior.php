<?php

declare(strict_types=1);

namespace ZtdQuery\Config;

/**
 * Defines how queries referencing unknown tables/columns should be handled.
 */
enum UnknownSchemaBehavior: string
{
    /**
     * Pass through to MySQL as-is (current behavior).
     * MySQL will return an error for unknown tables/columns.
     */
    case Passthrough = 'passthrough';

    /**
     * Return an empty result set.
     * Use for production-like testing where unknown schema should be silently ignored.
     */
    case EmptyResult = 'empty';

    /**
     * Output a warning notice and return an empty result set.
     * Use for debugging during development.
     */
    case Notice = 'notice';

    /**
     * Throw an UnknownSchemaException.
     * Use for strict testing and fuzz testing.
     */
    case Exception = 'exception';
}
