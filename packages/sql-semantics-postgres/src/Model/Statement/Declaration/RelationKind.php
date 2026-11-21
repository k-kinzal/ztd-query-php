<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration;

/**
 * What kind of relation a declaration is.
 *
 * Statements such as SHOW CREATE TABLE or DROP VIEW behave differently for
 * a base table and a view; the declaration states which one it is.
 *
 * @visibility public
 * @example Reading the kind of a declared view
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->analyze('CREATE TABLE t (a INTEGER)')->declarations()[0]->kind // => \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind::BaseTable
 */
enum RelationKind
{
    case BaseTable;
    case View;
    case MaterializedView;
    case ForeignTable;
    case Sequence;
}
