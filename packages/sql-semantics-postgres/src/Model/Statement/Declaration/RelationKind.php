<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration;

/**
 * What kind of relation a declaration is.
 *
 * Statements such as SHOW CREATE TABLE or DROP VIEW behave differently for
 * a base table and a view; the declaration states which one it is.
 *
 * @visibility SqlSemantics
 */
enum RelationKind
{
    case BaseTable;
    case View;
    case MaterializedView;
    case ForeignTable;
    case Sequence;
}
