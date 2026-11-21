<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table;

/**
 * The outcome of resolving a relation name: a declaration, a common table, missing, conflicting, or undeclared.
 *
 * @visibility public
 * @example Reading the resolution of a named input
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $query = $semantics->analyze('SELECT a FROM t', [$table]);
 *     $query->facts->relation($query->inputRelation())->table instanceof \SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable // => true
 */
interface TableResolution
{
}
