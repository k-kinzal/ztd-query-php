<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering;

/**
 * Which family lowers each statement of the grammar.
 *
 * Rule: PG-STATEMENT-ROUTE-001. Scope: every alternative of `stmt` in the
 * shipped releases except the empty statement. PostgreSQL's parse nodes are
 * grouped by what they do, so the route is a fixed table from the statement
 * nonterminal to the family that owns its node; the hub consults it for
 * top-level statements and for statements nested in other statements.
 * Source: https://www.postgresql.org/docs/17/sql-commands.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class StatementRoutes
{
    /**
     * The family of each `stmt` production.
     */
    public const ROUTES = [
        'stmt: AlterEventTrigStmt' => Family::Table, 'stmt: AlterPolicyStmt' => Family::Table, 'stmt: AlterSeqStmt' => Family::Table,
        'stmt: AlterStatsStmt' => Family::Table, 'stmt: AlterTableStmt' => Family::Table, 'stmt: CreateAsStmt' => Family::Table,
        'stmt: CreateAssertionStmt' => Family::Table, 'stmt: CreateEventTrigStmt' => Family::Table, 'stmt: CreateForeignTableStmt' => Family::Table,
        'stmt: CreateMatViewStmt' => Family::Table, 'stmt: CreatePolicyStmt' => Family::Table, 'stmt: CreateSeqStmt' => Family::Table,
        'stmt: CreateStatsStmt' => Family::Table, 'stmt: CreateStmt' => Family::Table, 'stmt: CreateTrigStmt' => Family::Table,
        'stmt: IndexStmt' => Family::Table, 'stmt: RefreshMatViewStmt' => Family::Table, 'stmt: RuleStmt' => Family::Table,
        'stmt: TruncateStmt' => Family::Table, 'stmt: ViewStmt' => Family::Table,
        'stmt: AlterCollationStmt' => Family::Catalog, 'stmt: AlterCompositeTypeStmt' => Family::Catalog, 'stmt: AlterDatabaseSetStmt' => Family::Catalog,
        'stmt: AlterDatabaseStmt' => Family::Catalog, 'stmt: AlterDomainStmt' => Family::Catalog, 'stmt: AlterEnumStmt' => Family::Catalog,
        'stmt: AlterExtensionContentsStmt' => Family::Catalog, 'stmt: AlterExtensionStmt' => Family::Catalog, 'stmt: AlterFdwStmt' => Family::Catalog,
        'stmt: AlterForeignServerStmt' => Family::Catalog, 'stmt: AlterPublicationStmt' => Family::Catalog, 'stmt: AlterSubscriptionStmt' => Family::Catalog,
        'stmt: AlterTSConfigurationStmt' => Family::Catalog, 'stmt: AlterTSDictionaryStmt' => Family::Catalog, 'stmt: AlterTblSpcStmt' => Family::Catalog,
        'stmt: AlterTypeStmt' => Family::Catalog, 'stmt: AlterUserMappingStmt' => Family::Catalog, 'stmt: CreateAmStmt' => Family::Catalog,
        'stmt: CreateCastStmt' => Family::Catalog, 'stmt: CreateConversionStmt' => Family::Catalog, 'stmt: CreateDomainStmt' => Family::Catalog,
        'stmt: CreateExtensionStmt' => Family::Catalog, 'stmt: CreateFdwStmt' => Family::Catalog, 'stmt: CreateForeignServerStmt' => Family::Catalog,
        'stmt: CreatePLangStmt' => Family::Catalog, 'stmt: CreatePublicationStmt' => Family::Catalog, 'stmt: CreateSchemaStmt' => Family::Catalog,
        'stmt: CreateSubscriptionStmt' => Family::Catalog, 'stmt: CreateTableSpaceStmt' => Family::Catalog, 'stmt: CreateTransformStmt' => Family::Catalog,
        'stmt: CreateUserMappingStmt' => Family::Catalog, 'stmt: CreatedbStmt' => Family::Catalog, 'stmt: DropCastStmt' => Family::Catalog,
        'stmt: DropSubscriptionStmt' => Family::Catalog, 'stmt: DropTableSpaceStmt' => Family::Catalog, 'stmt: DropTransformStmt' => Family::Catalog,
        'stmt: DropUserMappingStmt' => Family::Catalog, 'stmt: DropdbStmt' => Family::Catalog, 'stmt: ImportForeignSchemaStmt' => Family::Catalog,
        'stmt: AlterDefaultPrivilegesStmt' => Family::Access, 'stmt: AlterGroupStmt' => Family::Access, 'stmt: AlterRoleSetStmt' => Family::Access,
        'stmt: AlterRoleStmt' => Family::Access, 'stmt: CreateGroupStmt' => Family::Access, 'stmt: CreateRoleStmt' => Family::Access,
        'stmt: CreateUserStmt' => Family::Access, 'stmt: DropOwnedStmt' => Family::Access, 'stmt: DropRoleStmt' => Family::Access,
        'stmt: GrantRoleStmt' => Family::Access, 'stmt: GrantStmt' => Family::Access, 'stmt: ReassignOwnedStmt' => Family::Access,
        'stmt: RevokeRoleStmt' => Family::Access, 'stmt: RevokeStmt' => Family::Access,
        'stmt: AlterFunctionStmt' => Family::Routine, 'stmt: AlterObjectDependsStmt' => Family::Routine, 'stmt: AlterObjectSchemaStmt' => Family::Routine,
        'stmt: AlterOpFamilyStmt' => Family::Routine, 'stmt: AlterOperatorStmt' => Family::Routine, 'stmt: AlterOwnerStmt' => Family::Routine,
        'stmt: CommentStmt' => Family::Routine, 'stmt: CreateFunctionStmt' => Family::Routine, 'stmt: CreateOpClassStmt' => Family::Routine,
        'stmt: CreateOpFamilyStmt' => Family::Routine, 'stmt: DefineStmt' => Family::Routine, 'stmt: DropOpClassStmt' => Family::Routine,
        'stmt: DropOpFamilyStmt' => Family::Routine, 'stmt: DropStmt' => Family::Routine, 'stmt: RemoveAggrStmt' => Family::Routine,
        'stmt: RemoveFuncStmt' => Family::Routine, 'stmt: RemoveOperStmt' => Family::Routine, 'stmt: RenameStmt' => Family::Routine,
        'stmt: SecLabelStmt' => Family::Routine,
        'stmt: AlterSystemStmt' => Family::Utility, 'stmt: AnalyzeStmt' => Family::Utility, 'stmt: CallStmt' => Family::Utility,
        'stmt: CheckPointStmt' => Family::Utility, 'stmt: ClusterStmt' => Family::Utility, 'stmt: ConstraintsSetStmt' => Family::Utility,
        'stmt: DiscardStmt' => Family::Utility, 'stmt: DoStmt' => Family::Utility, 'stmt: ExplainStmt' => Family::Utility,
        'stmt: ListenStmt' => Family::Utility, 'stmt: LoadStmt' => Family::Utility, 'stmt: LockStmt' => Family::Utility,
        'stmt: NotifyStmt' => Family::Utility, 'stmt: ReindexStmt' => Family::Utility, 'stmt: TransactionStmt' => Family::Utility,
        'stmt: UnlistenStmt' => Family::Utility, 'stmt: VacuumStmt' => Family::Utility, 'stmt: VariableResetStmt' => Family::Utility,
        'stmt: VariableSetStmt' => Family::Utility, 'stmt: VariableShowStmt' => Family::Utility,
        'stmt: ClosePortalStmt' => Family::Manipulation, 'stmt: CopyStmt' => Family::Manipulation, 'stmt: DeallocateStmt' => Family::Manipulation,
        'stmt: DeclareCursorStmt' => Family::Manipulation, 'stmt: DeleteStmt' => Family::Manipulation, 'stmt: ExecuteStmt' => Family::Manipulation,
        'stmt: FetchStmt' => Family::Manipulation, 'stmt: InsertStmt' => Family::Manipulation, 'stmt: MergeStmt' => Family::Manipulation,
        'stmt: PrepareStmt' => Family::Manipulation, 'stmt: UpdateStmt' => Family::Manipulation,
        'stmt: SelectStmt' => Family::Query,
    ];
}
