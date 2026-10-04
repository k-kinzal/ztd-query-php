<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Problem;

/**
 * The rules of PostgreSQL a grammatical definition can break, in the words of the server.
 *
 * A `%s` in a message stands for the name the problem is about.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html and the
 * reference pages of the other commands.
 *
 * @visibility public
 * @example Reading which rule a definition breaks
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int, a text)');
 *     $create->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule::DuplicateColumn
 */
enum DefinitionRule: string
{
    case DuplicateColumn = 'column "%s" specified more than once';
    case SystemColumnName = 'column name "%s" conflicts with a system column name';
    case MissingColumn = 'column "%s" does not exist';
    case MissingKeyColumn = 'column "%s" named in key does not exist';
    case MissingReferencedColumn = 'column "%s" referenced in foreign key constraint does not exist';
    case MultiplePrimaryKeys = 'multiple primary keys for table "%s" are not allowed';
    case ConflictingNullability = 'conflicting NULL/NOT NULL declarations for column "%s"';
    case MultipleDefaults = 'multiple default values specified for column "%s"';
    case MultipleIdentities = 'multiple identity specifications for column "%s"';
    case MultipleGenerations = 'multiple generation clauses specified for column "%s"';
    case DefaultAndIdentity = 'both default and identity specified for column "%s"';
    case DefaultAndGeneration = 'both default and generation expression specified for column "%s"';
    case IdentityAndGeneration = 'both identity and generation expression specified for column "%s"';
    case GeneratedByDefault = 'for a generated column, GENERATED ALWAYS must be specified';
    case MisplacedAttribute = 'misplaced %s clause';
    case MultipleDeferrability = 'multiple DEFERRABLE/NOT DEFERRABLE clauses not allowed';
    case MultipleTiming = 'multiple INITIALLY IMMEDIATE/DEFERRED clauses not allowed';
    case DeferredNotDeferrable = 'constraint declared INITIALLY DEFERRED must be DEFERRABLE';
    case ConflictingAttributes = 'conflicting constraint properties';
    case CannotDefer = '%s constraints cannot be marked DEFERRABLE';
    case CannotNotValid = '%s constraints cannot be marked NOT VALID';
    case CannotNoInherit = '%s constraints cannot be marked NO INHERIT';
    case MultipleCollations = 'multiple COLLATE clauses not allowed';
    case IdentityType = 'identity column type must be smallint, integer, or bigint';
    case MatchPartial = 'MATCH PARTIAL not yet implemented';
    case SetColumnsOnUpdate = 'a column list with %s is only supported for ON DELETE actions';
    case NotBoolean = 'argument of %s must be type boolean';
    case SerialArray = 'array of serial is not implemented';
    case PartitionStrategy = 'unrecognized partitioning strategy "%s"';
    case HashBoundOption = 'unrecognized hash partition bound specification "%s"';
    case ColumnReferenceInBound = 'cannot use column reference in partition bound expression';
    case UnloggedView = 'views cannot be unlogged because they do not have storage';
    case ViewColumnCount = 'CREATE VIEW specifies more column names than columns';
    case TableColumnCount = 'too many column names were specified';
    case Unimplemented = '%s is not yet implemented';
    case RowSecurityOption = 'unrecognized row security option "%s"';
    case EventName = 'unrecognized event name "%s"';
    case FilterVariable = 'unrecognized filter variable "%s"';
    case InvalidSequenceOption = 'invalid sequence option %s';
}
