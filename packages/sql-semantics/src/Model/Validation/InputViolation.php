<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

/**
 * A classified SQL request whose operands cannot form a valid semantic operation.
 * @visibility public
 * @example Inspecting an invalid assignment
 *     \SqlSemantics\Model\Validation\InputViolation::AssignmentWidth->value // => 'assignment-column-count'
 */
enum InputViolation: string
{
    case OwnershipRole = 'ownership-role';
    case RoutineLanguage = 'routine-language';
    case StorageName = 'storage-name';
    case StorageOption = 'storage-option';
    case DatabaseName = 'database-name';
    case DatabaseReadOnly = 'database-read-only';
    case DatabaseEncryption = 'database-encryption';
    case DatabaseCharacterName = 'database-character-name';
    case ServerOption = 'server-option';
    case MappingRole = 'mapping-role';
    case MappingOption = 'mapping-option';
    case WrapperFunction = 'wrapper-function';
    case WrapperOption = 'wrapper-option';
    case RelationName = 'relation-name';
    case SpatialReferenceId = 'spatial-reference-id';
    case SpatialAttribute = 'spatial-attribute';
    case TypeParameterCount = 'type-parameter-count';
    case TypeModifier = 'type-modifier';
    case RoutineName = 'routine-name';
    case AggregateArgumentMode = 'aggregate-argument-mode';
    case AggregateVariadicSignature = 'aggregate-variadic-signature';
    case DiscardedValue = 'discarded-value';
    case HistogramTarget = 'histogram-target';
    case HistogramImport = 'histogram-import';
    case HistogramBuckets = 'histogram-buckets';
    case TemporalOperand = 'temporal-operand';
    case TableColumns = 'table-columns';
    case XaIdentifier = 'xa-identifier';
    case DeleteTarget = 'delete-target';
    case ExtractionField = 'extraction-field';
    case QueryOperand = 'query-operand';
    case FunctionArity = 'function-arity';
    case ReindexOption = 'reindex-option';
    case ConcurrentSystemReindex = 'concurrent-system-reindex';
    case OrderedSetWindow = 'ordered-set-window';
    case CteColumnCount = 'cte-column-count';
    case DuplicateCte = 'duplicate-cte';
    case DefaultContext = 'default-context';
    case ForeignKeyWidth = 'foreign-key-column-count';
    case ConcurrentIndexDrop = 'invalid-concurrent-index-drop';
    case JsonOption = 'json-option';
    case XmlOption = 'xml-option';
    case CursorOptions = 'cursor-options';
    case ScalarQueryWidth = 'scalar-query-width';
    case ComparisonWidth = 'comparison-width';
    case JoinedMutationPagination = 'joined-mutation-pagination';
    case TriggerQualifiedTarget = 'qualified-trigger-target';
    case TriggerIndexHint = 'trigger-index-hint';
    case AddedColumnKey = 'added-column-key';
    case MultiplePrimaryKeys = 'multiple-primary-keys';
    case OutputPosition = 'invalid-output-position';
    case ExplainOption = 'invalid-explain-option';
    case ExplainSetting = 'invalid-explain-setting';
    case ExplainCombination = 'invalid-explain-combination';
    case AssignmentWidth = 'assignment-column-count';
    case TupleSource = 'tuple-source';
    case InsertWidth = 'insert-column-count';
    case ValuesWidth = 'values-column-count';
    case SetWidth = 'set-column-count';
    case AlterAlgorithm = 'invalid-alter-algorithm';
    case AlterLock = 'invalid-alter-lock';
    case ConcurrentEmptyRefresh = 'concurrent-empty-refresh';
    /** @var array<string, string> */
    private const MESSAGES = [
        'ownership-role' => 'An ownership selector requires a concrete role or session role reference, excluding public and the reserved name none.',
        'routine-language' => 'A stored routine language requires a nonempty name.',
        'storage-name' => 'Storage DDL requires nonempty object and engine names.',
        'storage-option' => 'Storage DDL cannot repeat the engine option or a consecutive legacy NO_WAIT request.',
        'database-name' => 'A database operation requires a nonempty database name.',
        'database-read-only' => 'Database READ ONLY requires DEFAULT, zero, or one, and repeated requests must agree.',
        'database-encryption' => 'Database encryption requires Y or N.',
        'database-character-name' => 'A database character default requires a nonempty name.',
        'server-option' => 'Initial foreign server options require unique names.',
        'mapping-role' => 'A user mapping requires a nonempty role name other than the reserved name none.',
        'mapping-option' => 'Initial user mapping options require unique names.',
        'wrapper-function' => 'A foreign-data wrapper cannot specify a support function more than once.',
        'wrapper-option' => 'Initial foreign-data wrapper options require unique names.',
        'relation-name' => 'A relation name requires at most three identifier components without subscripts or wildcards.',
        'spatial-reference-id' => 'Spatial reference system DDL requires a nonzero unsigned 32-bit SRID and unsigned 32-bit organization identifiers.',
        'spatial-attribute' => 'A spatial definition requires exactly one NAME and DEFINITION and no duplicate attributes.',
        'type-parameter-count' => 'The built-in type does not accept this number of modifier operands.',
        'type-modifier' => 'PostgreSQL type modifiers require simple numeric or text constants or unqualified identifiers.',
        'routine-name' => 'A routine name requires identifier components without subscripts or wildcards.',
        'aggregate-argument-mode' => 'An aggregate signature accepts only input and variadic arguments.',
        'aggregate-variadic-signature' => 'A variadic direct argument requires one variadic aggregated argument of the same declared type.',
        'discarded-value' => 'DO requires scalar expressions and cannot expand table columns without a table input.',
        'histogram-target' => 'A histogram request requires exactly one target table.',
        'histogram-import' => 'Imported histogram data describes exactly one column.',
        'histogram-buckets' => 'A histogram bucket limit must be an integer from 1 to 1024.',
        'temporal-operand' => 'Temporal arithmetic requires one scalar temporal input and one scalar interval quantity.',
        'table-columns' => 'A table declaration requires at least one column in this SQL dialect.',
        'xa-identifier' => 'An XA identifier requires bounded byte-string components and a nonnegative integer format identifier.',
        'delete-target' => 'A MySQL DELETE destination requires a named table reference.',
        'extraction-field' => 'EXTRACT requires a field defined by the database language.',
        'query-operand' => 'A query operand requires a SELECT, VALUES, or TABLE operation.',
        'function-arity' => 'The function or conditional operation requires its declared number of arguments.',
        'reindex-option' => 'REINDEX requires a known option and a value in its declared domain.',
        'concurrent-system-reindex' => 'System-table indexes cannot be rebuilt concurrently.',
        'ordered-set-window' => 'An ordered-set aggregate cannot be used as a window function.',
        'default-context' => 'DEFAULT requires a column write or a setting assignment.',
        'foreign-key-column-count' => 'A foreign key requires matching local and referenced column counts.',
        'invalid-concurrent-index-drop' => 'Concurrent index deletion requires one index and cannot cascade.',
        'json-option' => 'The JSON option is outside the domain accepted by this operation.',
        'xml-option' => 'The XML column option is unknown or specified more than once.',
        'cursor-options' => 'A cursor cannot request both SCROLL and NO SCROLL.',
        'scalar-query-width' => 'A scalar subquery requires exactly one result column.',
        'comparison-width' => 'Compared row operands must have equal widths.',
        'joined-mutation-pagination' => 'A MySQL joined mutation cannot specify ORDER BY or LIMIT.',
        'qualified-trigger-target' => 'A SQLite trigger mutation requires an unqualified target name.',
        'trigger-index-hint' => 'A SQLite trigger mutation cannot specify an index hint.',
        'added-column-key' => 'SQLite ADD COLUMN cannot declare a PRIMARY KEY or UNIQUE constraint.',
        'multiple-primary-keys' => 'A table can declare only one primary key.',
        'invalid-output-position' => 'ORDER BY position is outside the result.',
        'invalid-explain-option' => 'The EXPLAIN option is not defined by this database language.',
        'invalid-explain-setting' => 'The EXPLAIN option requires a value in its declared domain.',
        'invalid-explain-combination' => 'The EXPLAIN options have incompatible execution requirements.',
        'assignment-column-count' => 'Assignment destinations and values must have the same width.',
        'tuple-source' => 'A tuple assignment requires a row constructor or a row-producing query.',
        'insert-column-count' => 'INSERT destinations and input values must have the same width.',
        'values-column-count' => 'VALUES rows must have the same width.',
        'set-column-count' => 'Set-operation operands must have the same result width.',
        'invalid-alter-algorithm' => 'The requested ALTER algorithm is not defined by this database language.',
        'invalid-alter-lock' => 'The requested ALTER lock mode is not defined by this database language.',
        'concurrent-empty-refresh' => 'A concurrent materialized view refresh cannot leave the view without data.',
        'cte-column-count' => 'CTE aliases must match the declared query result positions.',
        'duplicate-cte' => 'A WITH clause cannot define the same relation name twice.',
    ];

    /**
     * Describes the operand invariant identified by this diagnosis.
     */
    public function message(): string
    {
        return self::MESSAGES[$this->value];
    }
}
