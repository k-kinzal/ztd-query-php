<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

/**
 * What kind of relation a declaration is.
 *
 * Statements such as SHOW CREATE TABLE or DROP VIEW behave differently for
 * a base table and a view; the declaration states which one it is.
 *
 * @visibility public
 * @example Reading the kind of a declared view
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $semantics->analyze('CREATE TABLE t (a INTEGER)')->declarations()[0]->kind // => \SqlSemantics\Statement\Declaration\RelationKind::BaseTable
 */
enum RelationKind
{
    case BaseTable;
    case View;
    case MaterializedView;
    case ForeignTable;
    case Sequence;
}
