<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Statement\Node;
use Tests\Generator\MySql\Statement\Account\AlterDefaultRoleGenerator;
use Tests\Generator\MySql\Statement\Account\AlterRegistrationGenerator;
use Tests\Generator\MySql\Statement\Account\AlterUserGenerator;
use Tests\Generator\MySql\Statement\Account\CreateRoleGenerator;
use Tests\Generator\MySql\Statement\Account\CreateUserGenerator;
use Tests\Generator\MySql\Statement\Account\DropRoleGenerator;
use Tests\Generator\MySql\Statement\Account\DropUserGenerator;
use Tests\Generator\MySql\Statement\Account\ExpireUserPasswordsGenerator;
use Tests\Generator\MySql\Statement\Account\Option\AccountOptionGenerator;
use Tests\Generator\MySql\Statement\Account\Option\ResourceLimitGenerator;
use Tests\Generator\MySql\Statement\Account\Option\TlsConditionGenerator;
use Tests\Generator\MySql\Statement\Account\Option\TlsRequirementGenerator;
use Tests\Generator\MySql\Statement\Account\Option\UserCommentGenerator;
use Tests\Generator\MySql\Statement\Account\PasswordGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantAsGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantOptionRightGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantProxyGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantRolesGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Item\AllPrivilegesGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Item\DynamicPrivilegeGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Item\GrantedRoleGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Item\StaticPrivilegeGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevelGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\DatabaseLevelGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\GlobalLevelGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\Level\ObjectLevelGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\RevokeAllGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\RevokePrivilegesGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\RevokeProxyGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\RevokeRolesGenerator;
use Tests\Generator\MySql\Statement\Account\RenameUserGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\AlterResourceGroupGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\CpuRangeGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\CreateResourceGroupGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\DropResourceGroupGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\ResourceGroupNameGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\SetResourceGroupGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\ThreadPriorityGenerator;
use Tests\Generator\MySql\Statement\Account\SetDefaultRoleGenerator;
use Tests\Generator\MySql\Statement\Account\SetPasswordGenerator;
use Tests\Generator\MySql\Statement\Account\SetRoleGenerator;
use Tests\Generator\MySql\Statement\Account\User\FactorChangeGenerator;
use Tests\Generator\MySql\Statement\Account\User\FactorStepGenerator;
use Tests\Generator\MySql\Statement\Account\User\GranteeGenerator;
use Tests\Generator\MySql\Statement\Account\User\IdentificationGenerator;
use Tests\Generator\MySql\Statement\Account\User\RoleSelectionGenerator;
use Tests\Generator\MySql\Statement\Account\User\SessionUserGenerator;
use Tests\Generator\MySql\Statement\Account\User\UserRenamingGenerator;
use Tests\Generator\MySql\Statement\Account\User\UserSpecificationGenerator;
use Tests\Generator\MySql\Statement\Alter\AlterTableGenerator;
use Tests\Generator\MySql\Statement\Alter\Column\AddColumnGenerator;
use Tests\Generator\MySql\Statement\Alter\Column\AddColumnsGenerator;
use Tests\Generator\MySql\Statement\Alter\Column\ChangeColumnGenerator;
use Tests\Generator\MySql\Statement\Alter\Column\ColumnPositionGenerator;
use Tests\Generator\MySql\Statement\Alter\Column\ColumnVisibilityGenerator;
use Tests\Generator\MySql\Statement\Alter\Column\DefaultSettingGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\AddConstraintGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\ConstraintEnforcementGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\ConvertCharsetGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\DropElementGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\ForceGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\RenameElementGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\RenameToGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\ReorderGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\SetTableOptionsGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\ToggleKeysGenerator;
use Tests\Generator\MySql\Statement\Alter\DropIndexGenerator;
use Tests\Generator\MySql\Statement\Alter\DropTableGenerator;
use Tests\Generator\MySql\Statement\Alter\Modifier\AlgorithmOptionGenerator;
use Tests\Generator\MySql\Statement\Alter\Modifier\LockOptionGenerator;
use Tests\Generator\MySql\Statement\Alter\Modifier\ValidationOptionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\AddPartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\CoalescePartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\DropPartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\ExchangePartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\MaintainPartitionsGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\PartitionByGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\RemovePartitioningGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\ReorganizePartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\SecondaryLoadGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\TablespaceCommandGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\UpgradePartitioningGenerator;
use Tests\Generator\MySql\Statement\Alter\RenameTableGenerator;
use Tests\Generator\MySql\Statement\Alter\TableRenamingGenerator;
use Tests\Generator\MySql\Statement\Alter\TargetTableGenerator;
use Tests\Generator\MySql\Statement\Alter\TruncateTableGenerator;
use Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator;
use Tests\Generator\MySql\Statement\Call\Aggregate\GroupConcatGenerator;
use Tests\Generator\MySql\Statement\Call\Aggregate\JsonObjectAggregateGenerator;
use Tests\Generator\MySql\Statement\Call\CallArgumentGenerator;
use Tests\Generator\MySql\Statement\Call\CharCallGenerator;
use Tests\Generator\MySql\Statement\Call\ClockCallGenerator;
use Tests\Generator\MySql\Statement\Call\ExtractGenerator;
use Tests\Generator\MySql\Statement\Call\FunctionCallGenerator;
use Tests\Generator\MySql\Statement\Call\Json\JsonResponseGenerator;
use Tests\Generator\MySql\Statement\Call\Json\JsonValueCallGenerator;
use Tests\Generator\MySql\Statement\Call\Json\NestedColumnsGenerator;
use Tests\Generator\MySql\Statement\Call\Json\OrdinalityColumnGenerator;
use Tests\Generator\MySql\Statement\Call\Json\PathColumnGenerator;
use Tests\Generator\MySql\Statement\Call\KeywordCallGenerator;
use Tests\Generator\MySql\Statement\Call\PositionGenerator;
use Tests\Generator\MySql\Statement\Call\Temporal\DateArithmeticGenerator;
use Tests\Generator\MySql\Statement\Call\Temporal\GetFormatGenerator;
use Tests\Generator\MySql\Statement\Call\Temporal\TimestampCallGenerator;
use Tests\Generator\MySql\Statement\Call\TrimGenerator;
use Tests\Generator\MySql\Statement\Call\Weight\WeightLevelGenerator;
use Tests\Generator\MySql\Statement\Call\Weight\WeightLevelRangeGenerator;
use Tests\Generator\MySql\Statement\Call\Weight\WeightStringGenerator;
use Tests\Generator\MySql\Statement\Call\Weight\WeightStringParametersGenerator;
use Tests\Generator\MySql\Statement\Call\Window\FrameBoundGenerator;
use Tests\Generator\MySql\Statement\Call\Window\FrameGenerator;
use Tests\Generator\MySql\Statement\Call\Window\RoutineVariableGenerator;
use Tests\Generator\MySql\Statement\Call\Window\WindowFunctionGenerator;
use Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator;
use Tests\Generator\MySql\Statement\Dml\AssignmentGenerator;
use Tests\Generator\MySql\Statement\Dml\DefaultRequestGenerator;
use Tests\Generator\MySql\Statement\Dml\DeleteGenerator;
use Tests\Generator\MySql\Statement\Dml\EvaluationGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerCloseGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerIndexReadGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerIndexSeekGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerOpenGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerScanGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\OpenHandlerGenerator;
use Tests\Generator\MySql\Statement\Dml\ImportTableGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\ColumnListGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertedRowGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertQueryGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertRowsGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertSetGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\RowAliasGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\ValuesClausesGenerator;
use Tests\Generator\MySql\Statement\Dml\Load\BulkOptionsGenerator;
use Tests\Generator\MySql\Statement\Dml\Load\FieldOptionGenerator;
use Tests\Generator\MySql\Statement\Dml\Load\LineOptionGenerator;
use Tests\Generator\MySql\Statement\Dml\Load\LoadInputGenerator;
use Tests\Generator\MySql\Statement\Dml\Load\LoadTableGenerator;
use Tests\Generator\MySql\Statement\Dml\Load\TextFileFormatGenerator;
use Tests\Generator\MySql\Statement\Dml\MultipleDeleteGenerator;
use Tests\Generator\MySql\Statement\Dml\Prepared\DeallocateGenerator;
use Tests\Generator\MySql\Statement\Dml\Prepared\ExecuteGenerator;
use Tests\Generator\MySql\Statement\Dml\Prepared\PrepareGenerator;
use Tests\Generator\MySql\Statement\Dml\ProcedureCallGenerator;
use Tests\Generator\MySql\Statement\Dml\UpdateGenerator;
use Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator;
use Tests\Generator\MySql\Statement\EmptyStatementGenerator;
use Tests\Generator\MySql\Statement\Expression\Access\DefaultOfColumnGenerator;
use Tests\Generator\MySql\Statement\Expression\Access\FullTextSearchGenerator;
use Tests\Generator\MySql\Statement\Expression\Access\InsertedColumnGenerator;
use Tests\Generator\MySql\Statement\Expression\Access\JsonExtractionGenerator;
use Tests\Generator\MySql\Statement\Expression\Access\OdbcEscapeGenerator;
use Tests\Generator\MySql\Statement\Expression\Branching\CaseBranchGenerator;
use Tests\Generator\MySql\Statement\Expression\Branching\CaseExpressionGenerator;
use Tests\Generator\MySql\Statement\Expression\ComparisonGenerator;
use Tests\Generator\MySql\Statement\Expression\Conversion\AtTimeZoneGenerator;
use Tests\Generator\MySql\Statement\Expression\Conversion\CastGenerator;
use Tests\Generator\MySql\Statement\Expression\Conversion\CharsetConversionGenerator;
use Tests\Generator\MySql\Statement\Expression\GroupedGenerator;
use Tests\Generator\MySql\Statement\Expression\GroupedRowGenerator;
use Tests\Generator\MySql\Statement\Expression\LogicalGenerator;
use Tests\Generator\MySql\Statement\Expression\NotGenerator;
use Tests\Generator\MySql\Statement\Expression\NullTestGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\BinaryCastGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\CollatedGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\ConcatenationGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\IntervalAdditionGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\IntervalArithmeticGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\IntervalGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\UnaryGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\BetweenGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\InListGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\LikeGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\MemberOfGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\RegexpGenerator;
use Tests\Generator\MySql\Statement\Expression\Predicate\SoundsLikeGenerator;
use Tests\Generator\MySql\Statement\Expression\RowGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\ExistsGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\InQueryGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\QuantifiedComparisonGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\RowSubqueryGenerator;
use Tests\Generator\MySql\Statement\Expression\Subquery\ScalarSubqueryGenerator;
use Tests\Generator\MySql\Statement\Expression\TruthTestGenerator;
use Tests\Generator\MySql\Statement\Literal\BooleanLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\ByteSizeGenerator;
use Tests\Generator\MySql\Statement\Literal\NullLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\MySql\Statement\Literal\RadixLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\SignedLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\TemporalLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;
use Tests\Generator\MySql\Statement\Name\CharsetNameGenerator;
use Tests\Generator\MySql\Statement\Name\CollationNameGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnUseGenerator;
use Tests\Generator\MySql\Statement\Name\CurrentUserGenerator;
use Tests\Generator\MySql\Statement\Name\TableWildcardGenerator;
use Tests\Generator\MySql\Statement\Partition\AllPartitionsGenerator;
use Tests\Generator\MySql\Statement\Partition\Bound\LessThanGenerator;
use Tests\Generator\MySql\Statement\Partition\Bound\PartitionMaximumGenerator;
use Tests\Generator\MySql\Statement\Partition\Bound\PartitionRowGenerator;
use Tests\Generator\MySql\Statement\Partition\Bound\ValuesInGenerator;
use Tests\Generator\MySql\Statement\Partition\Bound\ValuesInRowsGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\ColumnsMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\DeferredMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\ExpressionMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\HashMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\KeyMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\NamedPartitionsGenerator;
use Tests\Generator\MySql\Statement\Partition\PartitionClauseGenerator;
use Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator;
use Tests\Generator\MySql\Statement\Partition\PartitionOptionGenerator;
use Tests\Generator\MySql\Statement\Partition\SubpartitionDefinitionGenerator;
use Tests\Generator\MySql\Statement\Partition\SubpartitioningGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\GroupingGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\LateOrderingGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\OutputOrdinalGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\ProcedureAnalyseGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\ProgramVariableGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\WindowDefinitionGenerator;
use Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoDumpfileGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoOutfileGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoVariablesGenerator;
use Tests\Generator\MySql\Statement\Query\Locking\LockingClauseGenerator;
use Tests\Generator\MySql\Statement\Query\OrderItemGenerator;
use Tests\Generator\MySql\Statement\Query\ParenthesizedQueryGenerator;
use Tests\Generator\MySql\Statement\Query\QueryExpressionGenerator;
use Tests\Generator\MySql\Statement\Query\QueryStatementGenerator;
use Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator;
use Tests\Generator\MySql\Statement\Query\SelectGenerator;
use Tests\Generator\MySql\Statement\Query\Set\LeadingUnionGenerator;
use Tests\Generator\MySql\Statement\Query\Set\OrderedSetOperationGenerator;
use Tests\Generator\MySql\Statement\Query\Set\SetOperationGenerator;
use Tests\Generator\MySql\Statement\Query\StarGenerator;
use Tests\Generator\MySql\Statement\Query\ValueRowGenerator;
use Tests\Generator\MySql\Statement\Query\ValuesQueryGenerator;
use Tests\Generator\MySql\Statement\Query\With\CommonTableExpressionGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredBodyGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredValueRowGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredValuesQueryGenerator;
use Tests\Generator\MySql\Statement\Query\With\WithGenerator;
use Tests\Generator\MySql\Statement\QueryRootGenerator;
use Tests\Generator\MySql\Statement\Relation\DerivedTableGenerator;
use Tests\Generator\MySql\Statement\Relation\DualGenerator;
use Tests\Generator\MySql\Statement\Relation\EscapedRelationGenerator;
use Tests\Generator\MySql\Statement\Relation\Hint\IndexHintGenerator;
use Tests\Generator\MySql\Statement\Relation\Hint\PrimaryIndexGenerator;
use Tests\Generator\MySql\Statement\Relation\Hint\TableSampleGenerator;
use Tests\Generator\MySql\Statement\Relation\JoinedTableGenerator;
use Tests\Generator\MySql\Statement\Relation\JsonTableGenerator;
use Tests\Generator\MySql\Statement\Relation\NestedRelationGenerator;
use Tests\Generator\MySql\Statement\Relation\OdbcJoinGenerator;
use Tests\Generator\MySql\Statement\Relation\TableListGenerator;
use Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator;
use Tests\Generator\MySql\Statement\Replication\Filter\ChangeReplicationFilterGenerator;
use Tests\Generator\MySql\Statement\Replication\Filter\DatabaseRewriteGenerator;
use Tests\Generator\MySql\Statement\Replication\Filter\ReplicationFilterGenerator;
use Tests\Generator\MySql\Statement\Replication\Group\CredentialOptionGenerator;
use Tests\Generator\MySql\Statement\Replication\Group\StartGroupReplicationGenerator;
use Tests\Generator\MySql\Statement\Replication\Group\StopGroupReplicationGenerator;
use Tests\Generator\MySql\Statement\Replication\Log\BinlogEventGenerator;
use Tests\Generator\MySql\Statement\Replication\Log\PurgeLogsBeforeGenerator;
use Tests\Generator\MySql\Statement\Replication\Log\PurgeLogsToGenerator;
use Tests\Generator\MySql\Statement\Replication\Replica\ReplicaUntilGenerator;
use Tests\Generator\MySql\Statement\Replication\Replica\StartReplicaGenerator;
use Tests\Generator\MySql\Statement\Replication\Replica\StopReplicaGenerator;
use Tests\Generator\MySql\Statement\Replication\Reset\ResetBinaryLogsGenerator;
use Tests\Generator\MySql\Statement\Replication\Reset\ResetGenerator;
use Tests\Generator\MySql\Statement\Replication\Reset\ResetPersistGenerator;
use Tests\Generator\MySql\Statement\Replication\Reset\ResetQueryCacheGenerator;
use Tests\Generator\MySql\Statement\Replication\Reset\ResetReplicaGenerator;
use Tests\Generator\MySql\Statement\Replication\Source\ChangeReplicationSourceGenerator;
use Tests\Generator\MySql\Statement\Replication\Source\ServerIdsGenerator;
use Tests\Generator\MySql\Statement\Replication\Source\SourceOptionGenerator;
use Tests\Generator\MySql\Statement\Routine\AlterEventGenerator;
use Tests\Generator\MySql\Statement\Routine\AlterRoutineGenerator;
use Tests\Generator\MySql\Statement\Routine\Characteristic\DataAccessGenerator;
use Tests\Generator\MySql\Statement\Routine\Characteristic\DeterminismGenerator;
use Tests\Generator\MySql\Statement\Routine\Characteristic\RoutineCommentGenerator;
use Tests\Generator\MySql\Statement\Routine\Characteristic\RoutineLanguageGenerator;
use Tests\Generator\MySql\Statement\Routine\Characteristic\SqlSecurityGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\ConditionNameGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\Diagnostics\ConditionDiagnosticsGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnosticsGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\Diagnostics\InformationItemGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnosticsGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\ErrorCodeGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\GeneralConditionGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\ResignalGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\SignalGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\SignalItemGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\SqlStateGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateEventGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateFunctionGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateLoadableFunctionGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateProcedureGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateTriggerGenerator;
use Tests\Generator\MySql\Statement\Routine\DollarQuotedTextGenerator;
use Tests\Generator\MySql\Statement\Routine\DropProgramGenerator;
use Tests\Generator\MySql\Statement\Routine\Event\OnceScheduleGenerator;
use Tests\Generator\MySql\Statement\Routine\Event\RecurringScheduleGenerator;
use Tests\Generator\MySql\Statement\Routine\ExternalBodyGenerator;
use Tests\Generator\MySql\Statement\Routine\ParameterListGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\BlockGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\ConditionDeclarationGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Cursor\CloseCursorGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Cursor\CursorDeclarationGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Cursor\FetchCursorGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Cursor\OpenCursorGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\DeferredAssignmentsGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\DeferredEvaluationGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\DeferredProjectionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\DeferredSelectionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Access\DeferredFullTextSearchGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranchGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredGroupConcatGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregateGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredCharGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredDateArithmeticGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredExtractGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredGetFormatGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCallGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredPositionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredTimestampGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredTrimGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredWeightParametersGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredWeightStringGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregateGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunctionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\DormantCallGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\Json\DeferredJsonValueCallGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Constructor\DeferredRowGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Conversion\DeferredBinaryCastGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCastGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCharsetConversionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredArithmeticGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredCallArgumentGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredCallGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredComparisonGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredGroupedGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredLikeGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredMemberOfGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredMembershipGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredSubqueryGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicateGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredTimeZoneGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredTruthTestGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredCollatedGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredIntervalGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredLogicalGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredNotGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredNullTestGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator\DeferredUnaryGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Predicate\DeferredBetweenGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Predicate\DeferredRegexpGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Predicate\DeferredSoundsLikeGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\Time\DeferredClockGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\ConditionalBranchGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\IfStatementGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\IterateGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\LeaveGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\LoopGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\RepeatLoopGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\ReturnStatementGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\SearchedCaseGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\SimpleCaseGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\WhileLoopGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\HandlerDeclarationGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredGroupingGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredOrderItemGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredQueryGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredWindowSpecGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameBoundGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredExplainGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredGroupStartGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredKillGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredPurgeGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\VariableDeclarationGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredColumnAssignmentGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredDefaultSettingGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredDeleteGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertedRowGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertQueryGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertRowsGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertSetGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredMultipleDeleteGenerator;
use Tests\Generator\MySql\Statement\Routine\Trigger\TriggerOrderGenerator;
use Tests\Generator\MySql\Statement\Routine\Trigger\TriggerTableGenerator;
use Tests\Generator\MySql\Statement\Server\Database\AlterDatabaseGenerator;
use Tests\Generator\MySql\Statement\Server\Database\CreateDatabaseGenerator;
use Tests\Generator\MySql\Statement\Server\Database\DatabaseCharsetGenerator;
use Tests\Generator\MySql\Statement\Server\Database\DatabaseCollationGenerator;
use Tests\Generator\MySql\Statement\Server\Database\DatabaseEncryptionGenerator;
use Tests\Generator\MySql\Statement\Server\Database\DatabaseReadOnlyGenerator;
use Tests\Generator\MySql\Statement\Server\Database\DropDatabaseGenerator;
use Tests\Generator\MySql\Statement\Server\Database\LegacyDatabaseNameGenerator;
use Tests\Generator\MySql\Statement\Server\Database\UpgradeDatabaseNameGenerator;
use Tests\Generator\MySql\Statement\Server\Flush\FlushGenerator;
use Tests\Generator\MySql\Statement\Server\Flush\FlushItemGenerator;
use Tests\Generator\MySql\Statement\Server\Flush\FlushTablesGenerator;
use Tests\Generator\MySql\Statement\Server\ForeignServer\AlterServerGenerator;
use Tests\Generator\MySql\Statement\Server\ForeignServer\CreateServerGenerator;
use Tests\Generator\MySql\Statement\Server\ForeignServer\DropServerGenerator;
use Tests\Generator\MySql\Statement\Server\ForeignServer\ServerOptionGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\AlterInstanceGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\CloneInstanceGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\CloneLocalGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\KillGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\RedoLogSwitchGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\ReloadKeyringGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\ReloadTlsGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\RotateMasterKeyGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\ShutdownGenerator;
use Tests\Generator\MySql\Statement\Server\KeyCache\CachedTableGenerator;
use Tests\Generator\MySql\Statement\Server\KeyCache\CacheIndexGenerator;
use Tests\Generator\MySql\Statement\Server\KeyCache\LoadIndexGenerator;
use Tests\Generator\MySql\Statement\Server\KeyCache\PreloadedTableGenerator;
use Tests\Generator\MySql\Statement\Server\Lock\LockInstanceGenerator;
use Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator;
use Tests\Generator\MySql\Statement\Server\Lock\TableLockGenerator;
use Tests\Generator\MySql\Statement\Server\Lock\UnlockInstanceGenerator;
use Tests\Generator\MySql\Statement\Server\Lock\UnlockTablesGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\AnalyzeTableGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\ChecksumTableGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\CheckTableGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\DropHistogramGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\LoadHistogramGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\MaintainedTableGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\OptimizeTableGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\RepairTableGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\UpdateHistogramGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\ComponentSettingGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\InstallComponentGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\InstallPluginGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\OnWordGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\UninstallComponentGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\UninstallPluginGenerator;
use Tests\Generator\MySql\Statement\Server\Spatial\CreateSpatialReferenceGenerator;
use Tests\Generator\MySql\Statement\Server\Spatial\DropSpatialReferenceGenerator;
use Tests\Generator\MySql\Statement\Server\Spatial\SpatialAttributeGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\AlterLogfileGroupGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\AlterTablespaceAccessGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\AlterTablespaceDatafileGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\AlterTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\AlterUndoTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\CreateLogfileGroupGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\CreateTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\CreateUndoTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\DropLogfileGroupGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\DropTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\DropUndoTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\LogFileGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\Option\CommentOptionGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\Option\EncryptionOptionGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\Option\EngineAttributeOptionGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\Option\NodegroupOptionGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\Option\SizeOptionGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\Option\WaitOptionGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\RenameTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\BeginGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\CommitGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\ReleaseSavepointGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\RollbackGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\RollbackToSavepointGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\SavepointGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaCommitGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaEndGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaPrepareGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaRecoverGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaRollbackGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaStartGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XidGenerator;
use Tests\Generator\MySql\Statement\Table\Column\CollateAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Column\ColumnDefinitionGenerator;
use Tests\Generator\MySql\Statement\Table\Column\CommentAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Column\DefaultExpressionGenerator;
use Tests\Generator\MySql\Statement\Table\Column\DefaultLiteralGenerator;
use Tests\Generator\MySql\Statement\Table\Column\EnforcementAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Column\EngineAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Column\FormatAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Column\GeneratedColumnGenerator;
use Tests\Generator\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumnGenerator;
use Tests\Generator\MySql\Statement\Table\Column\KeywordAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Column\OnUpdateGenerator;
use Tests\Generator\MySql\Statement\Table\Column\OrdinaryColumnGenerator;
use Tests\Generator\MySql\Statement\Table\Column\SridAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Column\StorageAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Column\Value\CurrentTimestampGenerator;
use Tests\Generator\MySql\Statement\Table\CreateIndexGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableLikeGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ArrayPartGenerator;
use Tests\Generator\MySql\Statement\Table\Key\CheckConstraintGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ColumnPartGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ConstraintNameGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ForeignKeyGenerator;
use Tests\Generator\MySql\Statement\Table\Key\IndexDefinitionGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexCommentGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexEngineAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexParserGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\IndexUsingGenerator;
use Tests\Generator\MySql\Statement\Table\Key\Option\KeyBlockSizeGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ReferencesGenerator;
use Tests\Generator\MySql\Statement\Table\Key\ReferentialActionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\AutoextendSizeOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\CharsetOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\CollationOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\InsertMethodOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\NumberOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\RowFormatOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\SecondaryEngineOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\StorageOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\TablespaceOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\TextOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\UnionOptionGenerator;
use Tests\Generator\MySql\Statement\Table\TableQueryGenerator;
use Tests\Generator\MySql\Statement\Type\BinaryGenerator;
use Tests\Generator\MySql\Statement\Type\CastTargetGenerator;
use Tests\Generator\MySql\Statement\Type\CharacterGenerator;
use Tests\Generator\MySql\Statement\Type\CharsetAttributeGenerator;
use Tests\Generator\MySql\Statement\Type\DecimalGenerator;
use Tests\Generator\MySql\Statement\Type\ElementaryGenerator;
use Tests\Generator\MySql\Statement\Type\EnumerationGenerator;
use Tests\Generator\MySql\Statement\Type\FloatingGenerator;
use Tests\Generator\MySql\Statement\Type\IntegralGenerator;
use Tests\Generator\MySql\Statement\Type\SpatialGenerator;
use Tests\Generator\MySql\Statement\Type\TemporalGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\DescribeTableGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\ExplainConnectionGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\ExplainGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\HelpGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\UseDatabaseGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\BareNameGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\NameAssignmentGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\NamesAssignmentGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\PasswordAssignmentGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\SetCharacterSetGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\SetNamesGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\SetTransactionGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\SetVariablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\SystemAssignmentGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\UserAssignmentGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\InspectedTableGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateEventGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateFunctionGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateProcedureGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateTriggerGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowFunctionCodeGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowFunctionStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowProcedureCodeGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowProcedureStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowBinaryLogsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowBinaryLogStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowBinlogEventsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowRelaylogEventsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowReplicasGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowReplicaStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowColumnsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateDatabaseGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateTableGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateViewGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowDatabasesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowEventsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowKeysGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowOpenTablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTableStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTriggersGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCharacterSetGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCollationGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCreateUserGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineCatalogGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineLogsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineMutexGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowGrantsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowPluginsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowPrivilegesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowProcesslistGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowVariablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowErrorCountGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowErrorsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowProfileGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowProfilesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowWarningCountGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowWarningsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\ShowLikeGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\ShowParseTreeGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\ShowWhereGenerator;
use Tests\Generator\MySql\Statement\Variable\SystemTargetGenerator;
use Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\VariableAssignmentGenerator;
use Tests\Generator\MySql\Statement\View\AlterViewGenerator;
use Tests\Generator\MySql\Statement\View\CreateViewGenerator;
use Tests\Generator\MySql\Statement\View\DropViewGenerator;
use Tests\Generator\MySql\Statement\View\ViewDefinitionGenerator;

/**
 * Generates Node values from the generators of its operands.
 *
 * @implements ErisGenerator<Node>
 */
final class NodeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Node> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Node from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 1, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 508, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 510, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 3, 3, 511, 511, 511, 511, 511, 508, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 0, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 0, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 1, 511, 3, 511, 511, 508, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 3, 1, 511, 511, 511, 508, 508, 511, 511, 508, 508, 511, 511, 3, 511, 511, 448, 511, 508, 511, 508, 511, 511, 510, 511, 511, 511, 510, 510, 511, 511, 511, 511, 511, 511, 511, 511, 508, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 508, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 504, 511, 511, 511, 511, 511, 508, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 508, 511, 511, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 508, 511, 511, 511, 511, 511, 511, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 508, 511, 511, 510, 510, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 508, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 508, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 1, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 510, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new AlterDefaultRoleGenerator($release),
                1 => new AlterRegistrationGenerator($release),
                2 => new AlterUserGenerator($release),
                3 => new CreateRoleGenerator($release),
                4 => new CreateUserGenerator($release),
                5 => new DropRoleGenerator($release),
                6 => new DropUserGenerator($release),
                7 => new ExpireUserPasswordsGenerator($release),
                8 => new AccountOptionGenerator($release),
                9 => new ResourceLimitGenerator($release),
                10 => new TlsConditionGenerator($release),
                11 => new TlsRequirementGenerator($release),
                12 => new UserCommentGenerator($release),
                13 => new PasswordGenerator($release),
                14 => new GrantAsGenerator($release),
                15 => new GrantOptionRightGenerator($release),
                16 => new GrantPrivilegesGenerator($release),
                17 => new GrantProxyGenerator($release),
                18 => new GrantRolesGenerator($release),
                19 => new AllPrivilegesGenerator($release),
                20 => new DynamicPrivilegeGenerator($release),
                21 => new GrantedRoleGenerator($release),
                22 => new StaticPrivilegeGenerator($release),
                23 => new CurrentDatabaseLevelGenerator($release),
                24 => new DatabaseLevelGenerator($release),
                25 => new GlobalLevelGenerator($release),
                26 => new ObjectLevelGenerator($release),
                27 => new RevokeAllGenerator($release),
                28 => new RevokePrivilegesGenerator($release),
                29 => new RevokeProxyGenerator($release),
                30 => new RevokeRolesGenerator($release),
                31 => new RenameUserGenerator($release),
                32 => new AlterResourceGroupGenerator($release),
                33 => new CpuRangeGenerator($release),
                34 => new CreateResourceGroupGenerator($release),
                35 => new DropResourceGroupGenerator($release),
                36 => new ResourceGroupNameGenerator($release),
                37 => new SetResourceGroupGenerator($release),
                38 => new ThreadPriorityGenerator($release),
                39 => new SetDefaultRoleGenerator($release),
                40 => new SetPasswordGenerator($release),
                41 => new SetRoleGenerator($release),
                42 => new FactorChangeGenerator($release),
                43 => new FactorStepGenerator($release),
                44 => new GranteeGenerator($release),
                45 => new IdentificationGenerator($release),
                46 => new RoleSelectionGenerator($release),
                47 => new SessionUserGenerator($release),
                48 => new UserRenamingGenerator($release),
                49 => new UserSpecificationGenerator($release),
                50 => new AlterTableGenerator($release, $depth - 1),
                51 => new AddColumnGenerator($release, $depth - 1),
                52 => new AddColumnsGenerator($release, $depth - 1),
                53 => new ChangeColumnGenerator($release, $depth - 1),
                54 => new ColumnPositionGenerator($release),
                55 => new ColumnVisibilityGenerator($release),
                56 => new DefaultSettingGenerator($release, $depth - 1),
                57 => new AddConstraintGenerator($release, $depth - 1),
                58 => new ConstraintEnforcementGenerator($release),
                59 => new ConvertCharsetGenerator($release),
                60 => new DropElementGenerator($release),
                61 => new ForceGenerator($release),
                62 => new \Tests\Generator\MySql\Statement\Alter\Command\IndexVisibilityGenerator($release),
                63 => new RenameElementGenerator($release),
                64 => new RenameToGenerator($release),
                65 => new ReorderGenerator($release),
                66 => new SetTableOptionsGenerator($release),
                67 => new ToggleKeysGenerator($release),
                68 => new DropIndexGenerator($release),
                69 => new DropTableGenerator($release),
                70 => new AlgorithmOptionGenerator($release),
                71 => new LockOptionGenerator($release),
                72 => new ValidationOptionGenerator($release),
                73 => new AddPartitionGenerator($release, $depth - 1),
                74 => new CoalescePartitionGenerator($release),
                75 => new DropPartitionGenerator($release),
                76 => new ExchangePartitionGenerator($release),
                77 => new MaintainPartitionsGenerator($release),
                78 => new PartitionByGenerator($release, $depth - 1),
                79 => new RemovePartitioningGenerator($release),
                80 => new ReorganizePartitionGenerator($release, $depth - 1),
                81 => new SecondaryLoadGenerator($release),
                82 => new TablespaceCommandGenerator($release),
                83 => new UpgradePartitioningGenerator($release),
                84 => new RenameTableGenerator($release),
                85 => new TableRenamingGenerator($release),
                86 => new TargetTableGenerator($release),
                87 => new TruncateTableGenerator($release),
                88 => new AggregateGenerator($release, $depth - 1),
                89 => new GroupConcatGenerator($release, $depth - 1),
                90 => new JsonObjectAggregateGenerator($release, $depth - 1),
                91 => new CallArgumentGenerator($release, $depth - 1),
                92 => new CharCallGenerator($release, $depth - 1),
                93 => new ClockCallGenerator($release),
                94 => new ExtractGenerator($release, $depth - 1),
                95 => new FunctionCallGenerator($release, $depth - 1),
                96 => new JsonResponseGenerator($release),
                97 => new JsonValueCallGenerator($release, $depth - 1),
                98 => new NestedColumnsGenerator($release, $depth - 1),
                99 => new OrdinalityColumnGenerator($release),
                100 => new PathColumnGenerator($release),
                101 => new KeywordCallGenerator($release, $depth - 1),
                102 => new PositionGenerator($release, $depth - 1),
                103 => new DateArithmeticGenerator($release, $depth - 1),
                104 => new GetFormatGenerator($release, $depth - 1),
                105 => new TimestampCallGenerator($release, $depth - 1),
                106 => new TrimGenerator($release, $depth - 1),
                107 => new WeightLevelGenerator($release),
                108 => new WeightLevelRangeGenerator($release),
                109 => new WeightStringGenerator($release, $depth - 1),
                110 => new WeightStringParametersGenerator($release, $depth - 1),
                111 => new FrameGenerator($release, $depth - 1),
                112 => new FrameBoundGenerator($release, $depth - 1),
                113 => new RoutineVariableGenerator($release),
                114 => new WindowFunctionGenerator($release, $depth - 1),
                115 => new WindowSpecGenerator($release, $depth - 1),
                116 => new AssignmentGenerator($release, $depth - 1),
                117 => new DefaultRequestGenerator($release),
                118 => new DeleteGenerator($release, $depth - 1),
                119 => new EvaluationGenerator($release, $depth - 1),
                120 => new HandlerCloseGenerator($release),
                121 => new HandlerIndexReadGenerator($release, $depth - 1),
                122 => new HandlerIndexSeekGenerator($release, $depth - 1),
                123 => new HandlerOpenGenerator($release),
                124 => new HandlerScanGenerator($release, $depth - 1),
                125 => new OpenHandlerGenerator($release),
                126 => new ImportTableGenerator($release),
                127 => new ColumnListGenerator($release),
                128 => new InsertIntoGenerator($release),
                129 => new InsertQueryGenerator($release, $depth - 1),
                130 => new InsertRowsGenerator($release, $depth - 1),
                131 => new InsertSetGenerator($release, $depth - 1),
                132 => new InsertedRowGenerator($release, $depth - 1),
                133 => new RowAliasGenerator($release),
                134 => new ValuesClausesGenerator($release, $depth - 1),
                135 => new BulkOptionsGenerator($release),
                136 => new FieldOptionGenerator($release),
                137 => new LineOptionGenerator($release),
                138 => new LoadInputGenerator($release),
                139 => new LoadTableGenerator($release, $depth - 1),
                140 => new TextFileFormatGenerator($release),
                141 => new MultipleDeleteGenerator($release, $depth - 1),
                142 => new DeallocateGenerator($release),
                143 => new ExecuteGenerator($release),
                144 => new PrepareGenerator($release),
                145 => new ProcedureCallGenerator($release, $depth - 1),
                146 => new UpdateGenerator($release, $depth - 1),
                147 => new WriteTargetGenerator($release),
                148 => new EmptyStatementGenerator($release),
                149 => new DefaultOfColumnGenerator($release),
                150 => new FullTextSearchGenerator($release, $depth - 1),
                151 => new InsertedColumnGenerator($release),
                152 => new JsonExtractionGenerator($release),
                153 => new OdbcEscapeGenerator($release, $depth - 1),
                154 => new CaseBranchGenerator($release, $depth - 1),
                155 => new CaseExpressionGenerator($release, $depth - 1),
                156 => new ComparisonGenerator($release, $depth - 1),
                157 => new AtTimeZoneGenerator($release, $depth - 1),
                158 => new CastGenerator($release, $depth - 1),
                159 => new CharsetConversionGenerator($release, $depth - 1),
                160 => new GroupedGenerator($release, $depth - 1),
                161 => new GroupedRowGenerator($release, $depth - 1),
                162 => new LogicalGenerator($release, $depth - 1),
                163 => new NotGenerator($release, $depth - 1),
                164 => new NullTestGenerator($release, $depth - 1),
                165 => new ArithmeticGenerator($release, $depth - 1),
                166 => new BinaryCastGenerator($release, $depth - 1),
                167 => new CollatedGenerator($release, $depth - 1),
                168 => new ConcatenationGenerator($release, $depth - 1),
                169 => new IntervalGenerator($release, $depth - 1),
                170 => new IntervalAdditionGenerator($release, $depth - 1),
                171 => new IntervalArithmeticGenerator($release, $depth - 1),
                172 => new UnaryGenerator($release, $depth - 1),
                173 => new BetweenGenerator($release, $depth - 1),
                174 => new InListGenerator($release, $depth - 1),
                175 => new LikeGenerator($release, $depth - 1),
                176 => new MemberOfGenerator($release, $depth - 1),
                177 => new RegexpGenerator($release, $depth - 1),
                178 => new SoundsLikeGenerator($release, $depth - 1),
                179 => new RowGenerator($release, $depth - 1),
                180 => new ExistsGenerator($release, $depth - 1),
                181 => new InQueryGenerator($release, $depth - 1),
                182 => new QuantifiedComparisonGenerator($release, $depth - 1),
                183 => new RowSubqueryGenerator($release, $depth - 1),
                184 => new ScalarSubqueryGenerator($release, $depth - 1),
                185 => new TruthTestGenerator($release, $depth - 1),
                186 => new BooleanLiteralGenerator($release),
                187 => new ByteSizeGenerator($release),
                188 => new NullLiteralGenerator($release),
                189 => new NumberLiteralGenerator($release),
                190 => new NumeralGenerator($release),
                191 => new \Tests\Generator\MySql\Statement\Literal\ParameterGenerator($release),
                192 => new RadixLiteralGenerator($release),
                193 => new SignedLiteralGenerator($release),
                194 => new StringLiteralGenerator($release),
                195 => new TemporalLiteralGenerator($release),
                196 => new TextGenerator($release),
                197 => new AccountNameGenerator($release),
                198 => new CharsetNameGenerator($release),
                199 => new CollationNameGenerator($release),
                200 => new ColumnNameGenerator($release),
                201 => new ColumnUseGenerator($release),
                202 => new CurrentUserGenerator($release),
                203 => new TableWildcardGenerator($release),
                204 => new AllPartitionsGenerator($release),
                205 => new LessThanGenerator($release, $depth - 1),
                206 => new PartitionMaximumGenerator($release),
                207 => new PartitionRowGenerator($release, $depth - 1),
                208 => new ValuesInGenerator($release, $depth - 1),
                209 => new ValuesInRowsGenerator($release, $depth - 1),
                210 => new ColumnsMethodGenerator($release),
                211 => new DeferredMethodGenerator($release, $depth - 1),
                212 => new ExpressionMethodGenerator($release, $depth - 1),
                213 => new HashMethodGenerator($release, $depth - 1),
                214 => new KeyMethodGenerator($release),
                215 => new NamedPartitionsGenerator($release),
                216 => new PartitionClauseGenerator($release, $depth - 1),
                217 => new PartitionDefinitionGenerator($release, $depth - 1),
                218 => new PartitionOptionGenerator($release),
                219 => new SubpartitionDefinitionGenerator($release),
                220 => new SubpartitioningGenerator($release, $depth - 1),
                221 => new QueryRootGenerator($release, $depth - 1),
                222 => new GroupingGenerator($release, $depth - 1),
                223 => new LateOrderingGenerator($release, $depth - 1),
                224 => new OutputOrdinalGenerator($release),
                225 => new ProcedureAnalyseGenerator($release),
                226 => new ProgramVariableGenerator($release),
                227 => new RowLimitGenerator($release),
                228 => new WindowDefinitionGenerator($release, $depth - 1),
                229 => new ExplicitTableGenerator($release),
                230 => new IntoDumpfileGenerator($release),
                231 => new IntoOutfileGenerator($release),
                232 => new IntoVariablesGenerator($release),
                233 => new LockingClauseGenerator($release),
                234 => new OrderItemGenerator($release, $depth - 1),
                235 => new ParenthesizedQueryGenerator($release, $depth - 1),
                236 => new QueryExpressionGenerator($release, $depth - 1),
                237 => new QueryStatementGenerator($release, $depth - 1),
                238 => new SelectGenerator($release, $depth - 1),
                239 => new SelectExpressionGenerator($release, $depth - 1),
                240 => new LeadingUnionGenerator($release, $depth - 1),
                241 => new OrderedSetOperationGenerator($release, $depth - 1),
                242 => new SetOperationGenerator($release, $depth - 1),
                243 => new StarGenerator($release),
                244 => new ValueRowGenerator($release, $depth - 1),
                245 => new ValuesQueryGenerator($release, $depth - 1),
                246 => new CommonTableExpressionGenerator($release, $depth - 1),
                247 => new DeferredBodyGenerator($release, $depth - 1),
                248 => new DeferredValueRowGenerator($release, $depth - 1),
                249 => new DeferredValuesQueryGenerator($release, $depth - 1),
                250 => new WithGenerator($release, $depth - 1),
                251 => new DerivedTableGenerator($release, $depth - 1),
                252 => new DualGenerator($release),
                253 => new EscapedRelationGenerator($release, $depth - 1),
                254 => new IndexHintGenerator($release),
                255 => new PrimaryIndexGenerator($release),
                256 => new TableSampleGenerator($release),
                257 => new JoinedTableGenerator($release, $depth - 1),
                258 => new JsonTableGenerator($release, $depth - 1),
                259 => new NestedRelationGenerator($release, $depth - 1),
                260 => new OdbcJoinGenerator($release, $depth - 1),
                261 => new TableListGenerator($release, $depth - 1),
                262 => new TableReferenceGenerator($release, $depth - 1),
                263 => new ChangeReplicationFilterGenerator($release),
                264 => new DatabaseRewriteGenerator($release),
                265 => new ReplicationFilterGenerator($release),
                266 => new CredentialOptionGenerator($release),
                267 => new StartGroupReplicationGenerator($release),
                268 => new StopGroupReplicationGenerator($release),
                269 => new BinlogEventGenerator($release),
                270 => new PurgeLogsBeforeGenerator($release, $depth - 1),
                271 => new PurgeLogsToGenerator($release),
                272 => new ReplicaUntilGenerator($release),
                273 => new StartReplicaGenerator($release),
                274 => new StopReplicaGenerator($release),
                275 => new ResetGenerator($release),
                276 => new ResetBinaryLogsGenerator($release),
                277 => new ResetPersistGenerator($release),
                278 => new ResetQueryCacheGenerator($release),
                279 => new ResetReplicaGenerator($release),
                280 => new ChangeReplicationSourceGenerator($release),
                281 => new ServerIdsGenerator($release),
                282 => new SourceOptionGenerator($release),
                283 => new AlterEventGenerator($release, $depth - 1),
                284 => new AlterRoutineGenerator($release),
                285 => new DataAccessGenerator($release),
                286 => new DeterminismGenerator($release),
                287 => new RoutineCommentGenerator($release),
                288 => new RoutineLanguageGenerator($release),
                289 => new SqlSecurityGenerator($release),
                290 => new ConditionNameGenerator($release),
                291 => new ConditionDiagnosticsGenerator($release, $depth - 1),
                292 => new GetDiagnosticsGenerator($release, $depth - 1),
                293 => new InformationItemGenerator($release),
                294 => new StatementDiagnosticsGenerator($release),
                295 => new ErrorCodeGenerator($release),
                296 => new GeneralConditionGenerator($release),
                297 => new ResignalGenerator($release, $depth - 1),
                298 => new SignalGenerator($release, $depth - 1),
                299 => new SignalItemGenerator($release, $depth - 1),
                300 => new SqlStateGenerator($release),
                301 => new CreateEventGenerator($release, $depth - 1),
                302 => new CreateFunctionGenerator($release, $depth - 1),
                303 => new CreateLoadableFunctionGenerator($release),
                304 => new CreateProcedureGenerator($release, $depth - 1),
                305 => new CreateTriggerGenerator($release, $depth - 1),
                306 => new DollarQuotedTextGenerator($release),
                307 => new DropProgramGenerator($release),
                308 => new OnceScheduleGenerator($release, $depth - 1),
                309 => new RecurringScheduleGenerator($release, $depth - 1),
                310 => new ExternalBodyGenerator($release),
                311 => new \Tests\Generator\MySql\Statement\Literal\ParameterGenerator($release),
                312 => new ParameterListGenerator($release),
                313 => new BlockGenerator($release, $depth - 1),
                314 => new ConditionDeclarationGenerator($release),
                315 => new CloseCursorGenerator($release),
                316 => new CursorDeclarationGenerator($release, $depth - 1),
                317 => new FetchCursorGenerator($release),
                318 => new OpenCursorGenerator($release),
                319 => new \Tests\Generator\MySql\Statement\Routine\Program\DeferredAssignmentGenerator($release, $depth - 1),
                320 => new DeferredAssignmentsGenerator($release, $depth - 1),
                321 => new DeferredEvaluationGenerator($release, $depth - 1),
                322 => new DeferredProjectionGenerator($release, $depth - 1),
                323 => new DeferredSelectionGenerator($release, $depth - 1),
                324 => new DeferredFullTextSearchGenerator($release, $depth - 1),
                325 => new DeferredCaseGenerator($release, $depth - 1),
                326 => new DeferredCaseBranchGenerator($release, $depth - 1),
                327 => new DeferredGroupConcatGenerator($release, $depth - 1),
                328 => new DeferredJsonObjectAggregateGenerator($release, $depth - 1),
                329 => new DeferredAggregateGenerator($release, $depth - 1),
                330 => new DeferredCharGenerator($release, $depth - 1),
                331 => new DeferredDateArithmeticGenerator($release, $depth - 1),
                332 => new DeferredExtractGenerator($release, $depth - 1),
                333 => new DeferredGetFormatGenerator($release, $depth - 1),
                334 => new DeferredKeywordCallGenerator($release, $depth - 1),
                335 => new DeferredPositionGenerator($release, $depth - 1),
                336 => new DeferredTimestampGenerator($release, $depth - 1),
                337 => new DeferredTrimGenerator($release, $depth - 1),
                338 => new DeferredWeightParametersGenerator($release, $depth - 1),
                339 => new DeferredWeightStringGenerator($release, $depth - 1),
                340 => new DeferredWindowAggregateGenerator($release, $depth - 1),
                341 => new DeferredWindowFunctionGenerator($release, $depth - 1),
                342 => new DormantCallGenerator($release, $depth - 1),
                343 => new DeferredJsonValueCallGenerator($release, $depth - 1),
                344 => new DeferredRowGenerator($release, $depth - 1),
                345 => new DeferredBinaryCastGenerator($release, $depth - 1),
                346 => new DeferredCastGenerator($release, $depth - 1),
                347 => new DeferredCharsetConversionGenerator($release, $depth - 1),
                348 => new DeferredArithmeticGenerator($release, $depth - 1),
                349 => new DeferredCallGenerator($release, $depth - 1),
                350 => new DeferredCallArgumentGenerator($release, $depth - 1),
                351 => new DeferredComparisonGenerator($release, $depth - 1),
                352 => new DeferredGroupedGenerator($release, $depth - 1),
                353 => new DeferredLikeGenerator($release, $depth - 1),
                354 => new DeferredMemberOfGenerator($release, $depth - 1),
                355 => new DeferredMembershipGenerator($release, $depth - 1),
                356 => new DeferredSubqueryGenerator($release, $depth - 1),
                357 => new DeferredSubqueryPredicateGenerator($release, $depth - 1),
                358 => new DeferredTimeZoneGenerator($release, $depth - 1),
                359 => new DeferredTruthTestGenerator($release, $depth - 1),
                360 => new \Tests\Generator\MySql\Statement\Routine\Program\DeferredAssignmentGenerator($release, $depth - 1),
                361 => new DeferredCollatedGenerator($release, $depth - 1),
                362 => new DeferredIntervalGenerator($release, $depth - 1),
                363 => new DeferredLogicalGenerator($release, $depth - 1),
                364 => new DeferredNotGenerator($release, $depth - 1),
                365 => new DeferredNullTestGenerator($release, $depth - 1),
                366 => new DeferredUnaryGenerator($release, $depth - 1),
                367 => new DeferredBetweenGenerator($release, $depth - 1),
                368 => new DeferredRegexpGenerator($release, $depth - 1),
                369 => new DeferredSoundsLikeGenerator($release, $depth - 1),
                370 => new DeferredClockGenerator($release),
                371 => new ConditionalBranchGenerator($release, $depth - 1),
                372 => new IfStatementGenerator($release, $depth - 1),
                373 => new IterateGenerator($release),
                374 => new LeaveGenerator($release),
                375 => new LoopGenerator($release, $depth - 1),
                376 => new RepeatLoopGenerator($release, $depth - 1),
                377 => new ReturnStatementGenerator($release, $depth - 1),
                378 => new SearchedCaseGenerator($release, $depth - 1),
                379 => new SimpleCaseGenerator($release, $depth - 1),
                380 => new WhileLoopGenerator($release, $depth - 1),
                381 => new HandlerDeclarationGenerator($release, $depth - 1),
                382 => new DeferredGroupingGenerator($release, $depth - 1),
                383 => new DeferredOrderItemGenerator($release, $depth - 1),
                384 => new DeferredQueryGenerator($release, $depth - 1),
                385 => new DeferredWindowSpecGenerator($release, $depth - 1),
                386 => new DeferredFrameGenerator($release, $depth - 1),
                387 => new DeferredFrameBoundGenerator($release, $depth - 1),
                388 => new DeferredExplainGenerator($release, $depth - 1),
                389 => new DeferredGroupStartGenerator($release),
                390 => new DeferredKillGenerator($release, $depth - 1),
                391 => new DeferredPurgeGenerator($release, $depth - 1),
                392 => new VariableDeclarationGenerator($release, $depth - 1),
                393 => new DeferredColumnAssignmentGenerator($release, $depth - 1),
                394 => new DeferredDefaultSettingGenerator($release, $depth - 1),
                395 => new DeferredDeleteGenerator($release, $depth - 1),
                396 => new DeferredInsertQueryGenerator($release, $depth - 1),
                397 => new DeferredInsertRowsGenerator($release, $depth - 1),
                398 => new DeferredInsertSetGenerator($release, $depth - 1),
                399 => new DeferredInsertedRowGenerator($release, $depth - 1),
                400 => new DeferredMultipleDeleteGenerator($release, $depth - 1),
                401 => new TriggerOrderGenerator($release),
                402 => new TriggerTableGenerator($release),
                403 => new AlterDatabaseGenerator($release),
                404 => new CreateDatabaseGenerator($release),
                405 => new DatabaseCharsetGenerator($release),
                406 => new DatabaseCollationGenerator($release),
                407 => new DatabaseEncryptionGenerator($release),
                408 => new DatabaseReadOnlyGenerator($release),
                409 => new DropDatabaseGenerator($release),
                410 => new LegacyDatabaseNameGenerator($release),
                411 => new UpgradeDatabaseNameGenerator($release),
                412 => new FlushGenerator($release),
                413 => new FlushItemGenerator($release),
                414 => new FlushTablesGenerator($release),
                415 => new AlterServerGenerator($release),
                416 => new CreateServerGenerator($release),
                417 => new DropServerGenerator($release),
                418 => new ServerOptionGenerator($release),
                419 => new AlterInstanceGenerator($release),
                420 => new CloneInstanceGenerator($release),
                421 => new CloneLocalGenerator($release),
                422 => new KillGenerator($release, $depth - 1),
                423 => new RedoLogSwitchGenerator($release),
                424 => new ReloadKeyringGenerator($release),
                425 => new ReloadTlsGenerator($release),
                426 => new RestartGenerator($release),
                427 => new RotateMasterKeyGenerator($release),
                428 => new ShutdownGenerator($release),
                429 => new CacheIndexGenerator($release),
                430 => new CachedTableGenerator($release),
                431 => new LoadIndexGenerator($release),
                432 => new PreloadedTableGenerator($release),
                433 => new LockInstanceGenerator($release),
                434 => new LockTablesGenerator($release),
                435 => new TableLockGenerator($release),
                436 => new UnlockInstanceGenerator($release),
                437 => new UnlockTablesGenerator($release),
                438 => new AnalyzeTableGenerator($release),
                439 => new CheckTableGenerator($release),
                440 => new ChecksumTableGenerator($release),
                441 => new DropHistogramGenerator($release),
                442 => new LoadHistogramGenerator($release),
                443 => new MaintainedTableGenerator($release),
                444 => new OptimizeTableGenerator($release),
                445 => new RepairTableGenerator($release),
                446 => new UpdateHistogramGenerator($release),
                447 => new ComponentSettingGenerator($release, $depth - 1),
                448 => new InstallComponentGenerator($release, $depth - 1),
                449 => new InstallPluginGenerator($release),
                450 => new OnWordGenerator($release),
                451 => new UninstallComponentGenerator($release),
                452 => new UninstallPluginGenerator($release),
                453 => new CreateSpatialReferenceGenerator($release),
                454 => new DropSpatialReferenceGenerator($release),
                455 => new SpatialAttributeGenerator($release),
                456 => new AlterLogfileGroupGenerator($release),
                457 => new AlterTablespaceGenerator($release),
                458 => new AlterTablespaceAccessGenerator($release),
                459 => new AlterTablespaceDatafileGenerator($release),
                460 => new AlterUndoTablespaceGenerator($release),
                461 => new CreateLogfileGroupGenerator($release),
                462 => new CreateTablespaceGenerator($release),
                463 => new CreateUndoTablespaceGenerator($release),
                464 => new DropLogfileGroupGenerator($release),
                465 => new DropTablespaceGenerator($release),
                466 => new DropUndoTablespaceGenerator($release),
                467 => new LogFileGenerator($release),
                468 => new CommentOptionGenerator($release),
                469 => new EncryptionOptionGenerator($release),
                470 => new EngineAttributeOptionGenerator($release),
                471 => new \Tests\Generator\MySql\Statement\Server\Storage\Option\EngineOptionGenerator($release),
                472 => new NodegroupOptionGenerator($release),
                473 => new SizeOptionGenerator($release),
                474 => new WaitOptionGenerator($release),
                475 => new RenameTablespaceGenerator($release),
                476 => new BeginGenerator($release),
                477 => new CommitGenerator($release),
                478 => new ReleaseSavepointGenerator($release),
                479 => new RollbackGenerator($release),
                480 => new RollbackToSavepointGenerator($release),
                481 => new SavepointGenerator($release),
                482 => new \Tests\Generator\MySql\Statement\Server\Transaction\StartTransactionGenerator($release),
                483 => new XaCommitGenerator($release),
                484 => new XaEndGenerator($release),
                485 => new XaPrepareGenerator($release),
                486 => new XaRecoverGenerator($release),
                487 => new XaRollbackGenerator($release),
                488 => new XaStartGenerator($release),
                489 => new XidGenerator($release),
                490 => new CollateAttributeGenerator($release),
                491 => new ColumnDefinitionGenerator($release, $depth - 1),
                492 => new CommentAttributeGenerator($release),
                493 => new DefaultExpressionGenerator($release, $depth - 1),
                494 => new DefaultLiteralGenerator($release),
                495 => new EnforcementAttributeGenerator($release),
                496 => new EngineAttributeGenerator($release),
                497 => new FormatAttributeGenerator($release),
                498 => new GeneratedColumnGenerator($release, $depth - 1),
                499 => new DeferredGeneratedColumnGenerator($release, $depth - 1),
                500 => new KeywordAttributeGenerator($release),
                501 => new OnUpdateGenerator($release),
                502 => new OrdinaryColumnGenerator($release, $depth - 1),
                503 => new SridAttributeGenerator($release),
                504 => new StorageAttributeGenerator($release),
                505 => new CurrentTimestampGenerator($release),
                506 => new CreateIndexGenerator($release, $depth - 1),
                507 => new CreateTableGenerator($release, $depth - 1),
                508 => new CreateTableLikeGenerator($release),
                509 => new ArrayPartGenerator($release, $depth - 1),
                510 => new CheckConstraintGenerator($release, $depth - 1),
                511 => new ColumnPartGenerator($release),
                512 => new ConstraintNameGenerator($release),
                513 => new \Tests\Generator\MySql\Statement\Table\Key\Deferred\ExpressionPartGenerator($release, $depth - 1),
                514 => new \Tests\Generator\MySql\Statement\Table\Key\Deferred\ExpressionPartGenerator($release, $depth - 1),
                515 => new ForeignKeyGenerator($release),
                516 => new IndexDefinitionGenerator($release, $depth - 1),
                517 => new IndexCommentGenerator($release),
                518 => new IndexEngineAttributeGenerator($release),
                519 => new IndexParserGenerator($release),
                520 => new IndexUsingGenerator($release),
                521 => new \Tests\Generator\MySql\Statement\Alter\Command\IndexVisibilityGenerator($release),
                522 => new KeyBlockSizeGenerator($release),
                523 => new ReferencesGenerator($release),
                524 => new ReferentialActionGenerator($release),
                525 => new AutoextendSizeOptionGenerator($release),
                526 => new CharsetOptionGenerator($release),
                527 => new CollationOptionGenerator($release),
                528 => new \Tests\Generator\MySql\Statement\Server\Storage\Option\EngineOptionGenerator($release),
                529 => new InsertMethodOptionGenerator($release),
                530 => new NumberOptionGenerator($release),
                531 => new RowFormatOptionGenerator($release),
                532 => new SecondaryEngineOptionGenerator($release),
                533 => new \Tests\Generator\MySql\Statement\Server\Transaction\StartTransactionGenerator($release),
                534 => new StorageOptionGenerator($release),
                535 => new TablespaceOptionGenerator($release),
                536 => new TextOptionGenerator($release),
                537 => new UnionOptionGenerator($release),
                538 => new TableQueryGenerator($release, $depth - 1),
                539 => new BinaryGenerator($release),
                540 => new CastTargetGenerator($release),
                541 => new CharacterGenerator($release),
                542 => new CharsetAttributeGenerator($release),
                543 => new DecimalGenerator($release),
                544 => new ElementaryGenerator($release),
                545 => new EnumerationGenerator($release),
                546 => new FloatingGenerator($release),
                547 => new IntegralGenerator($release),
                548 => new SpatialGenerator($release),
                549 => new TemporalGenerator($release),
                550 => new DescribeTableGenerator($release),
                551 => new ExplainGenerator($release, $depth - 1),
                552 => new ExplainConnectionGenerator($release),
                553 => new HelpGenerator($release),
                554 => new UseDatabaseGenerator($release),
                555 => new BareNameGenerator($release),
                556 => new NameAssignmentGenerator($release, $depth - 1),
                557 => new NamesAssignmentGenerator($release, $depth - 1),
                558 => new PasswordAssignmentGenerator($release),
                559 => new SetCharacterSetGenerator($release),
                560 => new SetNamesGenerator($release),
                561 => new SetTransactionGenerator($release),
                562 => new SetVariablesGenerator($release, $depth - 1),
                563 => new SystemAssignmentGenerator($release, $depth - 1),
                564 => new UserAssignmentGenerator($release, $depth - 1),
                565 => new InspectedTableGenerator($release),
                566 => new ShowCreateEventGenerator($release),
                567 => new ShowCreateFunctionGenerator($release),
                568 => new ShowCreateProcedureGenerator($release),
                569 => new ShowCreateTriggerGenerator($release),
                570 => new ShowFunctionCodeGenerator($release),
                571 => new ShowFunctionStatusGenerator($release, $depth - 1),
                572 => new ShowProcedureCodeGenerator($release),
                573 => new ShowProcedureStatusGenerator($release, $depth - 1),
                574 => new ShowBinaryLogStatusGenerator($release),
                575 => new ShowBinaryLogsGenerator($release),
                576 => new ShowBinlogEventsGenerator($release),
                577 => new ShowRelaylogEventsGenerator($release),
                578 => new ShowReplicaStatusGenerator($release),
                579 => new ShowReplicasGenerator($release),
                580 => new ShowColumnsGenerator($release, $depth - 1),
                581 => new ShowCreateDatabaseGenerator($release),
                582 => new ShowCreateTableGenerator($release),
                583 => new ShowCreateViewGenerator($release),
                584 => new ShowDatabasesGenerator($release, $depth - 1),
                585 => new ShowEventsGenerator($release, $depth - 1),
                586 => new ShowKeysGenerator($release, $depth - 1),
                587 => new ShowOpenTablesGenerator($release, $depth - 1),
                588 => new ShowTableStatusGenerator($release, $depth - 1),
                589 => new ShowTablesGenerator($release, $depth - 1),
                590 => new ShowTriggersGenerator($release, $depth - 1),
                591 => new ShowCharacterSetGenerator($release, $depth - 1),
                592 => new ShowCollationGenerator($release, $depth - 1),
                593 => new ShowCreateUserGenerator($release),
                594 => new ShowEngineCatalogGenerator($release),
                595 => new ShowEngineLogsGenerator($release),
                596 => new ShowEngineMutexGenerator($release),
                597 => new ShowEngineStatusGenerator($release),
                598 => new ShowGrantsGenerator($release),
                599 => new ShowPluginsGenerator($release),
                600 => new ShowPrivilegesGenerator($release),
                601 => new ShowProcesslistGenerator($release),
                602 => new ShowStatusGenerator($release, $depth - 1),
                603 => new ShowVariablesGenerator($release, $depth - 1),
                604 => new ShowErrorCountGenerator($release),
                605 => new ShowErrorsGenerator($release),
                606 => new ShowProfileGenerator($release),
                607 => new ShowProfilesGenerator($release),
                608 => new ShowWarningCountGenerator($release),
                609 => new ShowWarningsGenerator($release),
                610 => new ShowLikeGenerator($release),
                611 => new ShowParseTreeGenerator($release, $depth - 1),
                612 => new ShowWhereGenerator($release, $depth - 1),
                613 => new SystemTargetGenerator($release),
                614 => new SystemVariableGenerator($release),
                615 => new UserVariableGenerator($release),
                616 => new VariableAssignmentGenerator($release, $depth - 1),
                617 => new AlterViewGenerator($release, $depth - 1),
                618 => new CreateViewGenerator($release, $depth - 1),
                619 => new DropViewGenerator($release),
                620 => new ViewDefinitionGenerator($release, $depth - 1),
                621 => new ScriptGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 1, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 508, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 510, 511, 511, 511, 511, 511, 511, 511, 3, 511, 511, 511, 511, 511, 511, 511, 511, 3, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 3, 511, 511, 508, 511, 511, 511, 511, 511, 511, 510, 511, 511, 511, 510, 510, 511, 511, 511, 511, 511, 511, 511, 508, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 508, 511, 511, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 508, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 1, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 510, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new AlterDefaultRoleGenerator($release),
                1 => new AlterRegistrationGenerator($release),
                2 => new AlterUserGenerator($release),
                3 => new CreateRoleGenerator($release),
                4 => new CreateUserGenerator($release),
                5 => new DropRoleGenerator($release),
                6 => new DropUserGenerator($release),
                7 => new ExpireUserPasswordsGenerator($release),
                8 => new AccountOptionGenerator($release),
                9 => new ResourceLimitGenerator($release),
                10 => new TlsConditionGenerator($release),
                11 => new TlsRequirementGenerator($release),
                12 => new UserCommentGenerator($release),
                13 => new PasswordGenerator($release),
                14 => new GrantAsGenerator($release),
                15 => new GrantOptionRightGenerator($release),
                16 => new GrantPrivilegesGenerator($release),
                17 => new GrantProxyGenerator($release),
                18 => new GrantRolesGenerator($release),
                19 => new AllPrivilegesGenerator($release),
                20 => new DynamicPrivilegeGenerator($release),
                21 => new GrantedRoleGenerator($release),
                22 => new StaticPrivilegeGenerator($release),
                23 => new CurrentDatabaseLevelGenerator($release),
                24 => new DatabaseLevelGenerator($release),
                25 => new GlobalLevelGenerator($release),
                26 => new ObjectLevelGenerator($release),
                27 => new RevokeAllGenerator($release),
                28 => new RevokePrivilegesGenerator($release),
                29 => new RevokeProxyGenerator($release),
                30 => new RevokeRolesGenerator($release),
                31 => new RenameUserGenerator($release),
                32 => new AlterResourceGroupGenerator($release),
                33 => new CpuRangeGenerator($release),
                34 => new CreateResourceGroupGenerator($release),
                35 => new DropResourceGroupGenerator($release),
                36 => new ResourceGroupNameGenerator($release),
                37 => new SetResourceGroupGenerator($release),
                38 => new ThreadPriorityGenerator($release),
                39 => new SetDefaultRoleGenerator($release),
                40 => new SetPasswordGenerator($release),
                41 => new SetRoleGenerator($release),
                42 => new FactorChangeGenerator($release),
                43 => new FactorStepGenerator($release),
                44 => new GranteeGenerator($release),
                45 => new IdentificationGenerator($release),
                46 => new RoleSelectionGenerator($release),
                47 => new SessionUserGenerator($release),
                48 => new UserRenamingGenerator($release),
                49 => new UserSpecificationGenerator($release),
                50 => new ColumnPositionGenerator($release),
                51 => new ColumnVisibilityGenerator($release),
                52 => new ConstraintEnforcementGenerator($release),
                53 => new ConvertCharsetGenerator($release),
                54 => new DropElementGenerator($release),
                55 => new ForceGenerator($release),
                56 => new \Tests\Generator\MySql\Statement\Alter\Command\IndexVisibilityGenerator($release),
                57 => new RenameElementGenerator($release),
                58 => new RenameToGenerator($release),
                59 => new SetTableOptionsGenerator($release),
                60 => new ToggleKeysGenerator($release),
                61 => new DropIndexGenerator($release),
                62 => new DropTableGenerator($release),
                63 => new AlgorithmOptionGenerator($release),
                64 => new LockOptionGenerator($release),
                65 => new ValidationOptionGenerator($release),
                66 => new CoalescePartitionGenerator($release),
                67 => new DropPartitionGenerator($release),
                68 => new ExchangePartitionGenerator($release),
                69 => new MaintainPartitionsGenerator($release),
                70 => new RemovePartitioningGenerator($release),
                71 => new SecondaryLoadGenerator($release),
                72 => new TablespaceCommandGenerator($release),
                73 => new UpgradePartitioningGenerator($release),
                74 => new RenameTableGenerator($release),
                75 => new TableRenamingGenerator($release),
                76 => new TargetTableGenerator($release),
                77 => new TruncateTableGenerator($release),
                78 => new ClockCallGenerator($release),
                79 => new JsonResponseGenerator($release),
                80 => new OrdinalityColumnGenerator($release),
                81 => new PathColumnGenerator($release),
                82 => new WeightLevelGenerator($release),
                83 => new WeightLevelRangeGenerator($release),
                84 => new RoutineVariableGenerator($release),
                85 => new DefaultRequestGenerator($release),
                86 => new HandlerCloseGenerator($release),
                87 => new HandlerOpenGenerator($release),
                88 => new OpenHandlerGenerator($release),
                89 => new ImportTableGenerator($release),
                90 => new ColumnListGenerator($release),
                91 => new InsertIntoGenerator($release),
                92 => new RowAliasGenerator($release),
                93 => new BulkOptionsGenerator($release),
                94 => new FieldOptionGenerator($release),
                95 => new LineOptionGenerator($release),
                96 => new LoadInputGenerator($release),
                97 => new TextFileFormatGenerator($release),
                98 => new DeallocateGenerator($release),
                99 => new ExecuteGenerator($release),
                100 => new PrepareGenerator($release),
                101 => new WriteTargetGenerator($release),
                102 => new EmptyStatementGenerator($release),
                103 => new DefaultOfColumnGenerator($release),
                104 => new InsertedColumnGenerator($release),
                105 => new JsonExtractionGenerator($release),
                106 => new BooleanLiteralGenerator($release),
                107 => new ByteSizeGenerator($release),
                108 => new NullLiteralGenerator($release),
                109 => new NumberLiteralGenerator($release),
                110 => new NumeralGenerator($release),
                111 => new \Tests\Generator\MySql\Statement\Literal\ParameterGenerator($release),
                112 => new RadixLiteralGenerator($release),
                113 => new SignedLiteralGenerator($release),
                114 => new StringLiteralGenerator($release),
                115 => new TemporalLiteralGenerator($release),
                116 => new TextGenerator($release),
                117 => new AccountNameGenerator($release),
                118 => new CharsetNameGenerator($release),
                119 => new CollationNameGenerator($release),
                120 => new ColumnNameGenerator($release),
                121 => new ColumnUseGenerator($release),
                122 => new CurrentUserGenerator($release),
                123 => new TableWildcardGenerator($release),
                124 => new AllPartitionsGenerator($release),
                125 => new PartitionMaximumGenerator($release),
                126 => new ColumnsMethodGenerator($release),
                127 => new KeyMethodGenerator($release),
                128 => new NamedPartitionsGenerator($release),
                129 => new PartitionOptionGenerator($release),
                130 => new SubpartitionDefinitionGenerator($release),
                131 => new ProcedureAnalyseGenerator($release),
                132 => new ProgramVariableGenerator($release),
                133 => new RowLimitGenerator($release),
                134 => new ExplicitTableGenerator($release),
                135 => new IntoDumpfileGenerator($release),
                136 => new IntoOutfileGenerator($release),
                137 => new LockingClauseGenerator($release),
                138 => new StarGenerator($release),
                139 => new DualGenerator($release),
                140 => new PrimaryIndexGenerator($release),
                141 => new ChangeReplicationFilterGenerator($release),
                142 => new DatabaseRewriteGenerator($release),
                143 => new ReplicationFilterGenerator($release),
                144 => new CredentialOptionGenerator($release),
                145 => new StartGroupReplicationGenerator($release),
                146 => new StopGroupReplicationGenerator($release),
                147 => new BinlogEventGenerator($release),
                148 => new PurgeLogsToGenerator($release),
                149 => new ReplicaUntilGenerator($release),
                150 => new StartReplicaGenerator($release),
                151 => new StopReplicaGenerator($release),
                152 => new ResetGenerator($release),
                153 => new ResetBinaryLogsGenerator($release),
                154 => new ResetPersistGenerator($release),
                155 => new ResetQueryCacheGenerator($release),
                156 => new ResetReplicaGenerator($release),
                157 => new ChangeReplicationSourceGenerator($release),
                158 => new ServerIdsGenerator($release),
                159 => new SourceOptionGenerator($release),
                160 => new AlterRoutineGenerator($release),
                161 => new DataAccessGenerator($release),
                162 => new DeterminismGenerator($release),
                163 => new RoutineCommentGenerator($release),
                164 => new RoutineLanguageGenerator($release),
                165 => new SqlSecurityGenerator($release),
                166 => new ConditionNameGenerator($release),
                167 => new InformationItemGenerator($release),
                168 => new StatementDiagnosticsGenerator($release),
                169 => new ErrorCodeGenerator($release),
                170 => new GeneralConditionGenerator($release),
                171 => new SqlStateGenerator($release),
                172 => new CreateLoadableFunctionGenerator($release),
                173 => new DollarQuotedTextGenerator($release),
                174 => new DropProgramGenerator($release),
                175 => new ExternalBodyGenerator($release),
                176 => new \Tests\Generator\MySql\Statement\Literal\ParameterGenerator($release),
                177 => new ParameterListGenerator($release),
                178 => new ConditionDeclarationGenerator($release),
                179 => new CloseCursorGenerator($release),
                180 => new FetchCursorGenerator($release),
                181 => new OpenCursorGenerator($release),
                182 => new DeferredClockGenerator($release),
                183 => new IterateGenerator($release),
                184 => new LeaveGenerator($release),
                185 => new DeferredGroupStartGenerator($release),
                186 => new TriggerOrderGenerator($release),
                187 => new TriggerTableGenerator($release),
                188 => new AlterDatabaseGenerator($release),
                189 => new CreateDatabaseGenerator($release),
                190 => new DatabaseCharsetGenerator($release),
                191 => new DatabaseCollationGenerator($release),
                192 => new DatabaseEncryptionGenerator($release),
                193 => new DatabaseReadOnlyGenerator($release),
                194 => new DropDatabaseGenerator($release),
                195 => new LegacyDatabaseNameGenerator($release),
                196 => new UpgradeDatabaseNameGenerator($release),
                197 => new FlushGenerator($release),
                198 => new FlushItemGenerator($release),
                199 => new FlushTablesGenerator($release),
                200 => new AlterServerGenerator($release),
                201 => new CreateServerGenerator($release),
                202 => new DropServerGenerator($release),
                203 => new ServerOptionGenerator($release),
                204 => new AlterInstanceGenerator($release),
                205 => new CloneInstanceGenerator($release),
                206 => new CloneLocalGenerator($release),
                207 => new RedoLogSwitchGenerator($release),
                208 => new ReloadKeyringGenerator($release),
                209 => new ReloadTlsGenerator($release),
                210 => new RestartGenerator($release),
                211 => new RotateMasterKeyGenerator($release),
                212 => new ShutdownGenerator($release),
                213 => new CacheIndexGenerator($release),
                214 => new CachedTableGenerator($release),
                215 => new LoadIndexGenerator($release),
                216 => new PreloadedTableGenerator($release),
                217 => new LockInstanceGenerator($release),
                218 => new LockTablesGenerator($release),
                219 => new TableLockGenerator($release),
                220 => new UnlockInstanceGenerator($release),
                221 => new UnlockTablesGenerator($release),
                222 => new AnalyzeTableGenerator($release),
                223 => new CheckTableGenerator($release),
                224 => new ChecksumTableGenerator($release),
                225 => new DropHistogramGenerator($release),
                226 => new LoadHistogramGenerator($release),
                227 => new MaintainedTableGenerator($release),
                228 => new OptimizeTableGenerator($release),
                229 => new RepairTableGenerator($release),
                230 => new UpdateHistogramGenerator($release),
                231 => new InstallPluginGenerator($release),
                232 => new OnWordGenerator($release),
                233 => new UninstallComponentGenerator($release),
                234 => new UninstallPluginGenerator($release),
                235 => new CreateSpatialReferenceGenerator($release),
                236 => new DropSpatialReferenceGenerator($release),
                237 => new SpatialAttributeGenerator($release),
                238 => new AlterLogfileGroupGenerator($release),
                239 => new AlterTablespaceGenerator($release),
                240 => new AlterTablespaceAccessGenerator($release),
                241 => new AlterTablespaceDatafileGenerator($release),
                242 => new AlterUndoTablespaceGenerator($release),
                243 => new CreateLogfileGroupGenerator($release),
                244 => new CreateTablespaceGenerator($release),
                245 => new CreateUndoTablespaceGenerator($release),
                246 => new DropLogfileGroupGenerator($release),
                247 => new DropTablespaceGenerator($release),
                248 => new DropUndoTablespaceGenerator($release),
                249 => new LogFileGenerator($release),
                250 => new CommentOptionGenerator($release),
                251 => new EncryptionOptionGenerator($release),
                252 => new EngineAttributeOptionGenerator($release),
                253 => new \Tests\Generator\MySql\Statement\Server\Storage\Option\EngineOptionGenerator($release),
                254 => new NodegroupOptionGenerator($release),
                255 => new SizeOptionGenerator($release),
                256 => new WaitOptionGenerator($release),
                257 => new RenameTablespaceGenerator($release),
                258 => new BeginGenerator($release),
                259 => new CommitGenerator($release),
                260 => new ReleaseSavepointGenerator($release),
                261 => new RollbackGenerator($release),
                262 => new RollbackToSavepointGenerator($release),
                263 => new SavepointGenerator($release),
                264 => new \Tests\Generator\MySql\Statement\Server\Transaction\StartTransactionGenerator($release),
                265 => new XaCommitGenerator($release),
                266 => new XaEndGenerator($release),
                267 => new XaPrepareGenerator($release),
                268 => new XaRecoverGenerator($release),
                269 => new XaRollbackGenerator($release),
                270 => new XaStartGenerator($release),
                271 => new XidGenerator($release),
                272 => new CollateAttributeGenerator($release),
                273 => new CommentAttributeGenerator($release),
                274 => new DefaultLiteralGenerator($release),
                275 => new EnforcementAttributeGenerator($release),
                276 => new EngineAttributeGenerator($release),
                277 => new FormatAttributeGenerator($release),
                278 => new KeywordAttributeGenerator($release),
                279 => new OnUpdateGenerator($release),
                280 => new SridAttributeGenerator($release),
                281 => new StorageAttributeGenerator($release),
                282 => new CurrentTimestampGenerator($release),
                283 => new CreateTableLikeGenerator($release),
                284 => new ColumnPartGenerator($release),
                285 => new ConstraintNameGenerator($release),
                286 => new ForeignKeyGenerator($release),
                287 => new IndexCommentGenerator($release),
                288 => new IndexEngineAttributeGenerator($release),
                289 => new IndexParserGenerator($release),
                290 => new IndexUsingGenerator($release),
                291 => new \Tests\Generator\MySql\Statement\Alter\Command\IndexVisibilityGenerator($release),
                292 => new KeyBlockSizeGenerator($release),
                293 => new ReferencesGenerator($release),
                294 => new ReferentialActionGenerator($release),
                295 => new AutoextendSizeOptionGenerator($release),
                296 => new CharsetOptionGenerator($release),
                297 => new CollationOptionGenerator($release),
                298 => new \Tests\Generator\MySql\Statement\Server\Storage\Option\EngineOptionGenerator($release),
                299 => new InsertMethodOptionGenerator($release),
                300 => new NumberOptionGenerator($release),
                301 => new RowFormatOptionGenerator($release),
                302 => new SecondaryEngineOptionGenerator($release),
                303 => new \Tests\Generator\MySql\Statement\Server\Transaction\StartTransactionGenerator($release),
                304 => new StorageOptionGenerator($release),
                305 => new TablespaceOptionGenerator($release),
                306 => new TextOptionGenerator($release),
                307 => new UnionOptionGenerator($release),
                308 => new BinaryGenerator($release),
                309 => new CastTargetGenerator($release),
                310 => new CharacterGenerator($release),
                311 => new CharsetAttributeGenerator($release),
                312 => new DecimalGenerator($release),
                313 => new ElementaryGenerator($release),
                314 => new EnumerationGenerator($release),
                315 => new FloatingGenerator($release),
                316 => new IntegralGenerator($release),
                317 => new SpatialGenerator($release),
                318 => new TemporalGenerator($release),
                319 => new DescribeTableGenerator($release),
                320 => new ExplainConnectionGenerator($release),
                321 => new HelpGenerator($release),
                322 => new UseDatabaseGenerator($release),
                323 => new BareNameGenerator($release),
                324 => new PasswordAssignmentGenerator($release),
                325 => new SetCharacterSetGenerator($release),
                326 => new SetNamesGenerator($release),
                327 => new SetTransactionGenerator($release),
                328 => new InspectedTableGenerator($release),
                329 => new ShowCreateEventGenerator($release),
                330 => new ShowCreateFunctionGenerator($release),
                331 => new ShowCreateProcedureGenerator($release),
                332 => new ShowCreateTriggerGenerator($release),
                333 => new ShowFunctionCodeGenerator($release),
                334 => new ShowProcedureCodeGenerator($release),
                335 => new ShowBinaryLogStatusGenerator($release),
                336 => new ShowBinaryLogsGenerator($release),
                337 => new ShowBinlogEventsGenerator($release),
                338 => new ShowRelaylogEventsGenerator($release),
                339 => new ShowReplicaStatusGenerator($release),
                340 => new ShowReplicasGenerator($release),
                341 => new ShowCreateDatabaseGenerator($release),
                342 => new ShowCreateTableGenerator($release),
                343 => new ShowCreateViewGenerator($release),
                344 => new ShowCreateUserGenerator($release),
                345 => new ShowEngineCatalogGenerator($release),
                346 => new ShowEngineLogsGenerator($release),
                347 => new ShowEngineMutexGenerator($release),
                348 => new ShowEngineStatusGenerator($release),
                349 => new ShowGrantsGenerator($release),
                350 => new ShowPluginsGenerator($release),
                351 => new ShowPrivilegesGenerator($release),
                352 => new ShowProcesslistGenerator($release),
                353 => new ShowErrorCountGenerator($release),
                354 => new ShowErrorsGenerator($release),
                355 => new ShowProfileGenerator($release),
                356 => new ShowProfilesGenerator($release),
                357 => new ShowWarningCountGenerator($release),
                358 => new ShowWarningsGenerator($release),
                359 => new ShowLikeGenerator($release),
                360 => new SystemTargetGenerator($release),
                361 => new SystemVariableGenerator($release),
                362 => new UserVariableGenerator($release),
                363 => new DropViewGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (Node $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Node>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Node>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
