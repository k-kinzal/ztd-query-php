<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\ForeignData;

/**
 * Whether IMPORT FOREIGN SCHEMA imports only the listed tables or every table but them.
 *
 * Source: https://www.postgresql.org/docs/17/sql-importforeignschema.html.
 *
 * @visibility public
 * @example Spelling the restriction to listed tables
 *     \SqlSemantics\Platform\PostgreSql\Statement\ForeignData\ImportRestrictionKind::LimitTo->value // => 'LIMIT TO'
 */
enum ImportRestrictionKind: string
{
    case LimitTo = 'LIMIT TO';
    case Except = 'EXCEPT';
}
