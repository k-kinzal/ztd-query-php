<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;

/**
 * One clause that limits ALTER DEFAULT PRIVILEGES: IN SCHEMA or FOR ROLE.
 *
 * Mirrors the `DefElem` of `DefACLOption`; the server keeps one option for
 * the schemas and one for the roles and rejects a clause given twice.
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html.
 *
 * @visibility SqlSemantics
 */
interface DefaultScope extends Node
{
    /**
     * Answers the option of the server the clause fills: `schemas` or `roles`.
     */
    public function option(): string;
}
