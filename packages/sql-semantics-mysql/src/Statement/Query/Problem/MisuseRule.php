<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

/**
 * The rules of MySQL a grammatical query can break without naming a missing object.
 *
 * Each case holds the message of the server error it corresponds to.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Reading which rule a query breaks
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT *');
 *     $query->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::StarWithoutTables
 */
enum MisuseRule: string
{
    case StarWithoutTables = 'No tables used';
    case DuplicateAlias = 'Not unique table/alias';
    case DerivedWithoutAlias = 'Every derived table must have its own alias';
    case JsonTableSubquery = 'Incorrect arguments to JSON_TABLE';
    case TableFunctionWithoutAlias = 'Every table function must have an alias';
    case DuplicateCommonTable = 'Not unique table/alias in WITH';
    case RecursiveWithoutUnion = 'Recursive Common Table Expression should contain a UNION';
    case RecursiveWithoutAnchor = 'Recursive Common Table Expression should have one or more non-recursive query blocks followed by one or more recursive ones';
    case RecursiveReferencePosition = 'A recursive table must be referenced only once per query block and not in a subquery';
    case DuplicateColumn = 'Duplicate column name';
    case DuplicateWindow = 'Window name is defined more than once';
    case UnknownWindow = 'Window name is not defined';
    case WindowCircularity = 'There is a circularity in the window dependency graph.';
    case UnknownLockedTable = 'Table in the locking clause is not in the query';
    case RepeatedLockedTable = 'Table appears in multiple locking clauses';
    case AmbiguousJoinColumn = 'Column in from clause is ambiguous';
    case EmptyValuesRow = 'Each row of a VALUES clause must have at least one column, unless when used as source in an INSERT statement.';
}
