<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

/**
 * The modifier of KILL: CONNECTION or QUERY.
 *
 * Mirrors LEX::type of SQLCOM_KILL: QUERY sets ONLY_KILL_QUERY, which ends
 * only the statement the connection runs; CONNECTION, like no modifier,
 * ends the connection.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/kill.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope::Query->value // => 'QUERY'
 */
enum KillScope: string
{
    case Connection = 'CONNECTION';
    case Query = 'QUERY';
}
