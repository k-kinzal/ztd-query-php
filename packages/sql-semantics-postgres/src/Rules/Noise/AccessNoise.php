<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the access family that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class AccessNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            // "The ENCRYPTED keyword has no effect, but is accepted for backwards compatibility." https://www.postgresql.org/docs/17/sql-createrole.html
            'AlterOptRoleElem: ENCRYPTED PASSWORD Sconst' => [0],
            // "The PRIVILEGES key word is optional in PostgreSQL, though it is required by strict SQL." https://www.postgresql.org/docs/17/sql-grant.html
            'privileges: ALL PRIVILEGES' => [1],
            'privileges: ALL PRIVILEGES ( columnList )' => [1],
            // "The key word GROUP is still accepted in the command, but it is a noise word." (grantees; roles replaced users and groups) https://www.postgresql.org/docs/17/sql-grant.html
            'grantee: GROUP_P RoleSpec' => [0],
            // The synopsis writes `ON { [ TABLE ] table_name [, ...] | ALL TABLES IN SCHEMA ... }`: TABLE is optional before relation names. https://www.postgresql.org/docs/17/sql-grant.html
            'privilege_target: TABLE qualified_name_list' => [0],
        ];
    }
}
