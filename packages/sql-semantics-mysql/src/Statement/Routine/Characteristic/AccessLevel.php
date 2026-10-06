<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Characteristic;

/**
 * What a stored routine declares about its use of data: CONTAINS SQL, NO SQL, READS SQL DATA or MODIFIES SQL DATA.
 *
 * The characteristics are advisory; the server does not use them to
 * restrict the statements of the routine.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility public
 * @example Listing the levels
 *     count(\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\AccessLevel::cases()) // => 4
 */
enum AccessLevel
{
    case ContainsSql;
    case NoSql;
    case ReadsSqlData;
    case ModifiesSqlData;
}
