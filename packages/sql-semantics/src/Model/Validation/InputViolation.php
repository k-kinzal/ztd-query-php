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
    case EngineName = 'engine-name';
    case StorageOption = 'storage-option';
    case StorageValue = 'storage-value';
    case ServerDefinition = 'server-definition';
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
    case DerivedColumnCount = 'derived-column-count';
    case InsertRowAlias = 'insert-row-alias';
    case DerivedColumnName = 'derived-column-name';
    case QuantifiedOperator = 'quantified-operator';
    case OverlapsWidth = 'overlaps-width';
    case UniquePredicate = 'unique-predicate';
    case DuplicateCte = 'duplicate-cte';
    case DuplicateRelation = 'duplicate-relation';
    case TemporaryTableSchema = 'temporary-table-schema';
    case DefaultContext = 'default-context';
    case ForeignKeyWidth = 'foreign-key-column-count';
    case ConcurrentIndexDrop = 'invalid-concurrent-index-drop';
    case JsonOption = 'json-option';
    case XmlOption = 'xml-option';
    case XmlValueName = 'xml-value-name';
    case XmlSerializationTarget = 'xml-serialization-target';
    case CursorOptions = 'cursor-options';
    case ScalarQueryWidth = 'scalar-query-width';
    case ComparisonWidth = 'comparison-width';
    case JoinedMutationPagination = 'joined-mutation-pagination';
    case TriggerQualifiedTarget = 'qualified-trigger-target';
    case TriggerIndexHint = 'trigger-index-hint';
    case AddedColumnKey = 'added-column-key';
    case MultiplePrimaryKeys = 'multiple-primary-keys';
    case NullablePrimaryKey = 'nullable-primary-key';
    case NullDefault = 'null-default';
    case ConflictingNullability = 'conflicting-nullability';
    case AutoIncrementKey = 'autoincrement-key';
    case QueryBlockOption = 'query-block-option';
    case OutputPosition = 'invalid-output-position';
    case ExplainOption = 'invalid-explain-option';
    case ExplainSetting = 'invalid-explain-setting';
    case ExplainCombination = 'invalid-explain-combination';
    case AssignmentWidth = 'assignment-column-count';
    case AssignmentDestination = 'assignment-destination';
    case TupleSource = 'tuple-source';
    case InsertWidth = 'insert-column-count';
    case ValuesWidth = 'values-column-count';
    case SetWidth = 'set-column-count';
    case SetOperationLock = 'set-operation-lock';
    case ValuesLock = 'values-lock';
    case RepeatedLock = 'repeated-lock-target';
    case DerivedLockTarget = 'derived-lock-target';
    case TableCreationLock = 'table-creation-lock';
    case CommitAction = 'temporary-commit-action';
    case AlterAlgorithm = 'invalid-alter-algorithm';
    case AlterLock = 'invalid-alter-lock';
    case ConcurrentEmptyRefresh = 'concurrent-empty-refresh';
    case RoleName = 'role-name';
    case RoleReference = 'role-reference';
    case RoleOption = 'role-option';
    case RoleNumericOption = 'role-numeric-option';
    case RolePasswordEncryption = 'role-password-encryption';
    case RoleSetting = 'role-setting';
    case PrivilegeName = 'privilege-name';
    case PrivilegeTarget = 'privilege-target';
    case ColumnPrivilege = 'column-privilege';
    case LargeObjectId = 'large-object-id';
    case RoleGrantOption = 'role-grant-option';
    case DefaultPrivilegeScope = 'default-privilege-scope';
    case AuthenticationPlugin = 'authentication-plugin';
    case AuthenticationFactor = 'authentication-factor';
    case AccountLimit = 'account-limit';
    case CertificateRequirement = 'certificate-requirement';
    case RoleGrant = 'role-grant';
    case PrivilegeLevel = 'privilege-level';
    case RevokedCredential = 'revoked-credential';
    case CatalogObjectName = 'catalog-object-name';
    case OperatorSignature = 'operator-signature';
    case SecurityLabelTarget = 'security-label-target';
    case ConstraintAttribute = 'constraint-attribute';
    case ResetParameterValue = 'reset-parameter-value';
    case ChannelName = 'channel-name';
    case PartitionBound = 'partition-bound';
    case SequenceAttribute = 'sequence-attribute';
    case IdentityOption = 'identity-option';
    case ColumnCompression = 'column-compression';
    case ColumnStorage = 'column-storage';
    case ColumnPosition = 'column-position';
    case StatisticsTarget = 'statistics-target';
    case ForeignTableConstraint = 'foreign-table-constraint';
    case InstanceAction = 'instance-action';
    case ReplicationOption = 'replication-option';
    case ReplicaUntil = 'replica-until';
    case ReplicaCredentials = 'replica-credentials';
    case BinaryLogIndex = 'binary-log-index';
    case SourceCoordinates = 'source-coordinates';
    case ReplicationFilter = 'replication-filter';
    case CastConversion = 'cast-conversion';
    case SequenceOption = 'sequence-option';
    case ExistingIndexConstraint = 'existing-index-constraint';
    case PartitionKey = 'partition-key';
    case PartitionedTable = 'partitioned-table';
    case InheritedParent = 'inherited-parent';
    case ColumnOverride = 'column-override';
    case TableOption = 'table-option';
    case TableAlteration = 'table-alteration';
    case PartitionDefinition = 'partition-definition';
    case IndexPrefix = 'index-prefix';
    case DatabaseOption = 'database-option';
    case StoredSetting = 'stored-setting';
    case SchemaElement = 'schema-element';
    case MaintenanceOption = 'maintenance-option';
    case CopyOption = 'copy-option';
    case AnonymousBlock = 'anonymous-block';
    case ProcedureCall = 'procedure-call';
    case FunctionArgumentNotation = 'function-argument-notation';
    case SelectInto = 'select-into';
    case RowExpansion = 'row-expansion';
    case JsonPredicateOperand = 'json-predicate-operand';
    case FunctionTableColumns = 'function-table-columns';
    case TablespaceOption = 'tablespace-option';
    case DomainConstraint = 'domain-constraint';
    case SetOfDeclaration = 'set-of-declaration';
    case EnumLabel = 'enum-label';
    case DefinitionAttribute = 'definition-attribute';
    case DefinitionArgument = 'definition-argument';
    case DefinitionRequirement = 'definition-requirement';
    case OperatorClassMember = 'operator-class-member';
    case ConversionEncoding = 'conversion-encoding';
    case CompositeAttribute = 'composite-attribute';
    case CastDefinition = 'cast-definition';
    case ProgramReference = 'stored-program-reference';
    case SignalInformation = 'signal-information';
    case ResourceGroupOption = 'resource-group-option';
    case LoadOption = 'load-option';
    case SessionSetting = 'session-setting';
    case TriggerDefinition = 'trigger-definition';
    case RewriteRule = 'rewrite-rule';
    case RowSecurityPolicy = 'row-security-policy';
    case PublicationObject = 'publication-object';
    case ExtensionOption = 'extension-option';
    case RoutineDefinition = 'routine-definition';
    case RoutineAttribute = 'routine-attribute';
    case RoutineParameter = 'routine-parameter';
    case StatisticsDefinition = 'statistics-definition';
    case SequenceDefinition = 'sequence-definition';
    case ProgramDeclaration = 'stored-program-declaration';
    case ProgramLabel = 'stored-program-label';
    case ProgramObject = 'stored-program-object';
    case ProgramStatement = 'stored-program-statement';
    case ProgramDefinition = 'stored-program-definition';
    case SampleArguments = 'sample-arguments';
    case StoredTableClause = 'stored-table-clause';
    case RecursiveQueryClause = 'recursive-query-clause';
    case WindowModifier = 'window-modifier';

    /**
     * Describes the operand invariant identified by this diagnosis.
     */
    public function message(): string
    {
        return ViolationMessages::of($this);
    }
}
