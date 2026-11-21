<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Table;

/**
 * The outcome of resolving a relation name: a declaration, a common table, missing, conflicting, or undeclared.
 *
 * @visibility public
 * @example Reading the resolution of a named input
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $table = $semantics->inspect('CREATE TABLE t (a INTEGER)');
 *     $query = $semantics->inspect('SELECT a FROM t', [$table]);
 *     $query->facts->relation($query->inputRelation())->table instanceof \SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable // => true
 */
interface TableResolution
{
}
