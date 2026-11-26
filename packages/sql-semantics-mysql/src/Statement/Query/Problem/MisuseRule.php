<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

/**
 * The rules of MySQL a grammatical query can break without naming a missing object.
 *
 * Each case holds the message of the server error it corresponds to.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility SqlSemantics
 */
enum MisuseRule: string
{
    case StarWithoutTables = 'No tables used';
    case DuplicateAlias = 'Not unique table/alias';
    case DerivedWithoutAlias = 'Every derived table must have its own alias';
    case TableFunctionWithoutAlias = 'Every table function must have an alias';
    case DuplicateCommonTable = 'Not unique table/alias in WITH';
    case RecursiveWithoutAnchor = 'Recursive Common Table Expression should contain a UNION whose first operand does not refer to it';
    case DuplicateColumn = 'Duplicate column name';
    case DuplicateWindow = 'Window name is defined more than once';
    case UnknownWindow = 'Window name is not defined';
    case UnknownLockedTable = 'Table in the locking clause is not in the query';
    case AmbiguousJoinColumn = 'Column in from clause is ambiguous';
}
