<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Problem;

/**
 * The rules of PostgreSQL a grammatical query can break, in the words of the server.
 *
 * A `%s` in a message stands for the name the problem is about.
 * Source: https://www.postgresql.org/docs/17/sql-select.html.
 *
 * @visibility public
 * @example Reading which rule a query breaks
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT *');
 *     $query->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule::StarWithoutTables
 */
enum QueryMisuseRule: string
{
    case StarWithoutTables = 'SELECT * with no tables specified is not valid';
    case NotComposite = 'type %s is not composite';
    case MultipleOrderBy = 'multiple ORDER BY clauses not allowed';
    case MultipleLimit = 'multiple LIMIT clauses not allowed';
    case MultipleOffset = 'multiple OFFSET clauses not allowed';
    case MultipleWith = 'multiple WITH clauses not allowed';
    case CommaLimit = 'LIMIT #,# syntax is not supported';
    case TiesWithoutOrderBy = 'WITH TIES cannot be specified without ORDER BY clause';
    case TiesWithSkipLocked = 'SKIP LOCKED and WITH TIES options cannot be used together';
    case LockingWithSetOperation = 'FOR UPDATE is not allowed with UNION/INTERSECT/EXCEPT';
    case LockingWithDistinct = 'FOR UPDATE is not allowed with DISTINCT clause';
    case LockingWithGroupBy = 'FOR UPDATE is not allowed with GROUP BY clause';
    case LockingWithHaving = 'FOR UPDATE is not allowed with HAVING clause';
    case LockingOnValues = 'FOR UPDATE cannot be applied to VALUES';
    case LockedRelationNotInFrom = 'relation "%s" in FOR UPDATE clause not found in FROM clause';
    case NonIntegerConstant = 'non-integer constant in %s';
    case LockedRelationQualified = 'SELECT FOR UPDATE/SHARE must specify unqualified relation names';
    case MutualRecursion = 'mutual recursion between WITH items is not implemented';
    case RepeatedCommonColumn = 'common column name "%s" appears more than once in left or right table';
    case SetOperationOrderByExpression = 'invalid UNION/INTERSECT/EXCEPT ORDER BY clause';
    case DistinctOnOrderBy = 'SELECT DISTINCT ON expressions must match initial ORDER BY expressions';
    case IntoNotAllowed = 'SELECT ... INTO is not allowed here';
    case DuplicateCommonTable = 'WITH query name "%s" specified more than once';
    case RecursiveForm = 'recursive query "%s" does not have the form non-recursive-term UNION [ALL] recursive-term';
    case SearchOrCycleNotRecursive = 'WITH query "%s" is not recursive but has a SEARCH or CYCLE clause';
    case WithoutReturning = 'WITH query "%s" does not have a RETURNING clause';
    case UsingColumnRepeated = 'column name "%s" appears more than once in USING clause';
    case UsingColumnNotInLeft = 'column "%s" specified in USING clause does not exist in left table';
    case UsingColumnNotInRight = 'column "%s" specified in USING clause does not exist in right table';
    case RecordWithoutDefinitions = 'a column definition list is required for functions returning "record"';
    case DefinitionsForBaseType = 'a column definition list is only allowed for functions returning "record"';
    case UnrecognizedColumnOption = 'unrecognized column option "%s"';
    case RedundantNullability = 'conflicting or redundant NULL / NOT NULL declarations for column "%s"';
    case RepeatedDefault = 'only one DEFAULT value is allowed';
    case RepeatedPath = 'only one PATH value per column is allowed';
    case RepeatedOrdinality = 'only one FOR ORDINALITY column is allowed';
    case PathNotConstant = 'only string constants are supported in JSON_TABLE path specification';
    case SearchColumnMissing = 'search column "%s" not in WITH query column list';
    case CycleColumnMissing = 'cycle column "%s" not in WITH query column list';
    case TablesampleOnCommonTable = 'TABLESAMPLE clause can only be applied to tables and materialized views';
}
