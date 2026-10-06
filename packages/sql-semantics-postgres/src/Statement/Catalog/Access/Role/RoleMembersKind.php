<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

/**
 * The role options that list other roles: the value of a case is its keywords.
 *
 * ROLE and USER list the members of the new role, ADMIN lists members that
 * may grant the membership, IN ROLE and IN GROUP list the roles the new role
 * becomes a member of. USER and IN GROUP are obsolete spellings of ROLE and
 * IN ROLE; each spelling is kept as written.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility public
 * @example Reading the option an obsolete spelling fills
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleMembersKind::InGroup->option() // => 'addroleto'
 */
enum RoleMembersKind: string
{
    case User = 'USER';
    case Role = 'ROLE';
    case Admin = 'ADMIN';
    case InRole = 'IN ROLE';
    case InGroup = 'IN GROUP';

    /**
     * Answers the option of the server the spelling fills.
     */
    public function option(): string
    {
        return match ($this) {
            self::User, self::Role => 'rolemembers',
            self::Admin => 'adminmembers',
            self::InRole, self::InGroup => 'addroleto',
        };
    }
}
