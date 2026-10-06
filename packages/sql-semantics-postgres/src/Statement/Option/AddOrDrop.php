<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

/**
 * Whether a command adds members to an object or removes them, as `add_drop` writes it.
 *
 * Source: https://www.postgresql.org/docs/17/sql-alterextension.html, https://www.postgresql.org/docs/17/sql-altergroup.html.
 *
 * @visibility public
 * @example Spelling the removing choice
 *     \SqlSemantics\Platform\PostgreSql\Statement\Option\AddOrDrop::Drop->value // => 'DROP'
 */
enum AddOrDrop: string
{
    case Add = 'ADD';
    case Drop = 'DROP';
}
