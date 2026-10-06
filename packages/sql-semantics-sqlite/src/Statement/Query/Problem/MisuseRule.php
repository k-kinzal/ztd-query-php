<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

/**
 * The rules of SQLite a grammatical statement can break without naming a missing object.
 *
 * @visibility public
 * @example Reading which rule a statement breaks
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT *');
 *     $query->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule::StarWithoutTables
 */
enum MisuseRule: string
{
    case StarWithoutTables = 'no tables specified';
    case UnknownJoinType = 'unknown join type';
    case OnWithoutJoin = 'a JOIN clause is required before ON';
    case UsingWithoutJoin = 'a JOIN clause is required before USING';
    case NaturalJoinWithConstraint = 'a NATURAL join may not have an ON or USING clause';
    case OrderByBeforeCompound = 'ORDER BY clause should come after the compound operator not before';
    case LimitBeforeCompound = 'LIMIT clause should come after the compound operator not before';
    case HexLiteralTooBig = 'hex literal too big';
    case DecoratedColumnName = 'syntax error after column name';
    case DuplicateCommonTable = 'duplicate WITH table name';
    case CircularReference = 'circular reference';
    case QualifiedTriggerTarget = 'qualified table names are not allowed on INSERT, UPDATE, and DELETE statements within triggers';
    case IndexedTriggerTarget = 'the INDEXED BY and NOT INDEXED clauses are not allowed on UPDATE or DELETE statements within triggers';
    case RaiseOutsideTrigger = 'RAISE() may only be used within a trigger-program';
    case ParameterInTrigger = 'trigger cannot use variables';
    case TooManyValueColumns = 'row value misused';
}
