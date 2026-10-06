<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language;

/**
 * The kind of access method: an index access method or a table access method.
 *
 * Source: https://www.postgresql.org/docs/17/sql-create-access-method.html.
 *
 * @visibility public
 * @example Spelling the table kind
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\AccessMethodKind::Table->value // => 'TABLE'
 */
enum AccessMethodKind: string
{
    case Index = 'INDEX';
    case Table = 'TABLE';
}
