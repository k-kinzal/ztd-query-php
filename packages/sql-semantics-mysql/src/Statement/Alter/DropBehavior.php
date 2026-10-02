<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

/**
 * The RESTRICT or CASCADE keyword of a DROP statement or of ALTER TABLE ... DROP COLUMN.
 *
 * MySQL accepts both keywords and they do nothing; the keyword is kept as
 * written because it is part of the request.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-table.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior::Cascade->value // => 'CASCADE'
 */
enum DropBehavior: string
{
    case Restrict = 'RESTRICT';
    case Cascade = 'CASCADE';
}
