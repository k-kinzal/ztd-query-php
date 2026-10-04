<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Group;

/**
 * The connection options of START GROUP_REPLICATION (MySQL 8.0.21 and later).
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-group-replication.html.
 *
 * @visibility public
 * @example Reading the keyword of an option
 *     \SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::DefaultAuth->value // => 'DEFAULT_AUTH'
 */
enum Credential: string
{
    case User = 'USER';
    case Password = 'PASSWORD';
    case DefaultAuth = 'DEFAULT_AUTH';
}
