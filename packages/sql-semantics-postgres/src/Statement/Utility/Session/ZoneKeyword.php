<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

/**
 * The keyword values of SET TIME ZONE.
 *
 * LOCAL and DEFAULT both set the time zone to the value the session would
 * have without the command; the value is the keyword.
 * Source: https://www.postgresql.org/docs/17/sql-set.html.
 *
 * @visibility public
 * @example Reading the keyword of SET TIME ZONE LOCAL
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TIME ZONE LOCAL');
 *     $operation->statement->zone // => \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ZoneKeyword::Local
 */
enum ZoneKeyword: string
{
    case Default = 'DEFAULT';
    case Local = 'LOCAL';
}
