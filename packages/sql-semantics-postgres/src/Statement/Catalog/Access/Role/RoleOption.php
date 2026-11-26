<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;

/**
 * One option of CREATE ROLE or ALTER ROLE.
 *
 * Mirrors the `DefElem` the grammar builds for `CreateOptRoleElem` and
 * `AlterOptRoleElem`. Several spellings fill the same option of the server
 * (LOGIN and NOLOGIN both fill `canlogin`), and an option filled twice is
 * rejected, so every option tells which one it fills.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility SqlSemantics
 */
interface RoleOption extends Node
{
    /**
     * Answers the option of the server this spelling fills, or null when the server keeps none for it.
     */
    public function option(): ?string;
}
