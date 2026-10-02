<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Name;

/**
 * How a role specification designates its role.
 *
 * Source: https://www.postgresql.org/docs/17/sql-grant.html and PostgreSQL's `RoleSpecType`.
 *
 * @visibility public
 * @example Naming the pseudo-role that stands for every role
 *     \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Everyone->name // => 'Everyone'
 */
enum RoleSpecKind
{
    case Named;
    case Everyone;
    case CurrentRole;
    case CurrentUser;
    case SessionUser;
}
