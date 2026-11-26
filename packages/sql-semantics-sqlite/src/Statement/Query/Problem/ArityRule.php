<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

/**
 * The places where SQLite requires two column counts to agree.
 *
 * @visibility SqlSemantics
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
    case InListElement;
}
