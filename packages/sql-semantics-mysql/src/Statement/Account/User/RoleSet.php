<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

/**
 * Which roles a role selection denotes: the roles named, none, the account's default roles, or all granted roles.
 *
 * Mirrors the server's role_enum (ROLE_NAME, ROLE_NONE, ROLE_DEFAULT,
 * ROLE_ALL).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-role.html.
 *
 * @visibility public
 * @example Naming the selection of all roles
 *     \SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet::All->name // => 'All'
 */
enum RoleSet
{
    case Named;
    case None;
    case Default;
    case All;
}
