<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege;
use SqlSemantics\Statement\Node;

/**
 * The objects of a GRANT or REVOKE: named objects of one kind, or every object of a kind in schemas.
 *
 * Mirrors the `targtype`, `objtype` and `objects` of `GrantStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading the kind of the objects of a grant
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT USAGE ON SCHEMA app TO joe');
 *     $operation->statement->target->object() // => \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Schema
 */
interface PrivilegeTarget extends Node
{
    /**
     * Answers the kind of object the privileges are granted on.
     */
    public function object(): PrivilegeObjectKind;

    /**
     * Derives the facts of the objects for the privileges granted on them.
     *
     * @param list<Privilege> $privileges The privileges of the statement
     */
    public function deriveTarget(Derivation $derivation, array $privileges): void;
}
