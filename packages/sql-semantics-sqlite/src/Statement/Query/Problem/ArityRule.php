<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

/**
 * The places where SQLite requires two column counts to agree.
 *
 * @visibility public
 * @example Reading which counts disagree
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 UNION SELECT 2, 3');
 *     $query->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule::CompoundArms
 */
enum ArityRule
{
    case CompoundArms;
    case ValueRows;
    case InsertedValues;
    case CommonTableColumns;
    case RowComparison;
    case RowAssignment;
    case ScalarSubquery;
}
