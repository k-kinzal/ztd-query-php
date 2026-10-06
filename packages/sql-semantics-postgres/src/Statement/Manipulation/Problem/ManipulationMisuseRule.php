<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem;

/**
 * The rules of PostgreSQL a grammatical data-modifying, COPY, prepared-statement or cursor command can break, in the words of the server.
 *
 * A `%s` in a message stands for a subject of the problem, in order.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html, https://www.postgresql.org/docs/17/sql-update.html,
 * https://www.postgresql.org/docs/17/sql-merge.html, https://www.postgresql.org/docs/17/sql-copy.html,
 * https://www.postgresql.org/docs/17/sql-declare.html,
 * https://www.postgresql.org/docs/17/ddl-generated-columns.html.
 *
 * @visibility public
 * @example Reading which rule a statement breaks
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('INSERT INTO t (a, a) VALUES (1, 2)');
 *     $insert->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule::RepeatedInsertColumn
 */
enum ManipulationMisuseRule: string
{
    case UnknownTargetColumn = 'column "%s" of relation "%s" does not exist';
    case RepeatedInsertColumn = 'column "%s" specified more than once';
    case RepeatedAssignment = 'multiple assignments to same column "%s"';
    case SystemColumnAssignment = 'cannot assign to system column "%s"';
    case RowExpansion = 'row expansion via "*" is not supported here';
    case MoreExpressions = 'INSERT has more expressions than target columns';
    case MoreTargetColumns = 'INSERT has more target columns than expressions';
    case AssignmentType = 'column "%s" is of type %s but expression is of type %s';
    case MultipleAssignmentSource = 'source for a multiple-column UPDATE item must be a sub-SELECT or ROW() expression';
    case MultipleAssignmentArity = 'number of columns does not match number of values';
    case DefaultPlacement = 'DEFAULT is not allowed in this context';
    case MergeActionPlacement = 'MERGE_ACTION() can only be used in the RETURNING list of a MERGE command';
    case UnreachableWhen = 'unreachable WHEN clause specified after unconditional WHEN clause';
    case ConflictUpdateWithoutTarget = 'ON CONFLICT DO UPDATE requires inference specification or constraint name';
    case RepeatedRelationName = 'table name "%s" specified more than once';
    case CopyWhereWithTo = 'WHERE clause not allowed with COPY TO';
    case CopyProgramWithClient = 'STDIN/STDOUT not allowed with PROGRAM';
    case CopyWithoutReturning = 'COPY query must have a RETURNING clause';
    case CopySelectInto = 'COPY (SELECT INTO) is not supported';
    case CopyUnknownOption = 'option "%s" not recognized';
    case CopyRedundantOption = 'conflicting or redundant options';
    case CopyColumnNotCopied = '%s column "%s" not referenced by COPY';
    case CopyGeneratedColumn = 'column "%s" is a generated column';
    case GeneratedInsert = 'cannot insert a non-DEFAULT value into column "%s"';
    case GeneratedUpdate = 'column "%s" can only be updated to DEFAULT';
    case CursorScrollConflict = 'cannot specify both SCROLL and NO SCROLL';
    case CursorSensitivityConflict = 'cannot specify both INSENSITIVE and ASENSITIVE';
    case CursorModifyingWith = 'DECLARE CURSOR must not contain data-modifying statements in WITH';
}
