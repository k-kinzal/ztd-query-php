<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch;

/**
 * Whether a text search configuration gains mappings for token types or replaces their existing mappings.
 *
 * Source: https://www.postgresql.org/docs/17/sql-altertsconfig.html.
 *
 * @visibility public
 * @example Spelling the replacing change
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\MappingChange::Alter->value // => 'ALTER'
 */
enum MappingChange: string
{
    case Add = 'ADD';
    case Alter = 'ALTER';
}
