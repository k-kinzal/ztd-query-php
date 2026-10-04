<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults;

use SqlSemantics\Statement\Node;

/**
 * One clause that limits ALTER DEFAULT PRIVILEGES: IN SCHEMA or FOR ROLE.
 *
 * Mirrors the `DefElem` of `DefACLOption`; the server keeps one option for
 * the schemas and one for the roles and rejects a clause given twice.
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html.
 *
 * @visibility public
 * @example Telling which option of the server a clause fills
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES FOR USER joe GRANT SELECT ON TABLES TO ann');
 *     $operation->statement->scopes[0]->option() // => 'roles'
 */
interface DefaultScope extends Node
{
    /**
     * Answers the option of the server the clause fills: `schemas` or `roles`.
     */
    public function option(): string;
}
