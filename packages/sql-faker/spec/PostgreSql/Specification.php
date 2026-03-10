<?php

declare(strict_types=1);

namespace Spec\PostgreSql;

use LogicException;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Runtime;
use Spec\Specification\AbstractDialectSpecification;
use Spec\Subject\FamilyDefinition;
use Spec\Subject\FamilyRequest;
use Spec\Subject\SqlWitness;

/**
 * Spec-local PostgreSQL witness generation and family metadata.
 */
final class Specification extends AbstractDialectSpecification
{
    public function entryRules(): array
    {
        return ['stmtmulti'];
    }

    /**
     * Generates one PostgreSQL witness that satisfies the requested family constraints.
     */
    public function generateWitness(Runtime $runtime, FamilyRequest $request): SqlWitness
    {
        $this->assertFamilyParameters($request);

        return match ($request->familyId) {
            'postgresql.statement.any' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(seed: $seed, maxDepth: 8))),
            'postgresql.statement.select' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'SelectStmt', seed: $seed, maxDepth: 8))),
            'postgresql.statement.insert' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'InsertStmt', seed: $seed, maxDepth: 8))),
            'postgresql.statement.update' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'UpdateStmt', seed: $seed, maxDepth: 8))),
            'postgresql.statement.delete' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'DeleteStmt', seed: $seed, maxDepth: 8))),
            'postgresql.constraint.distinct_on' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'SelectStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_contains($sql, 'DISTINCT ON('),
                null,
                4096,
            ),
            'postgresql.constraint.alter_materialized_view' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterTableStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isAlterMaterializedViewWitness($sql),
                null,
                2048,
            ),
            'postgresql.constraint.alter_index.commands' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterIndexStmt', seed: $seed, maxDepth: 8)),
                null,
                null,
                2048,
            ),
            'postgresql.constraint.alter_view.commands' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterTableStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isAlterViewCommandWitness($sql),
                null,
                2048,
            ),
            'postgresql.constraint.alter_domain.add' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterDomainStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isAlterDomainAddWitness($sql),
            ),
            'postgresql.constraint.create_table_as.explicit_columns' => $this->generateExplicitCtasWitness($runtime, $request),
            'postgresql.constraint.alter_sequence' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterTableStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_starts_with($sql, 'ALTER SEQUENCE '),
                null,
                4096,
            ),
            'postgresql.constraint.alter_statistics' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterStatsStmt', seed: $seed, maxDepth: 8)),
            ),
            'postgresql.constraint.alter_type.options' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterTypeStmt', seed: $seed, maxDepth: 8)),
            ),
            'postgresql.constraint.alter_routine.options' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterFunctionStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isAlterRoutineOptionsWitness($sql),
                null,
                2048,
            ),
            'postgresql.constraint.alter_database.options' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterDatabaseStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isAlterDatabaseOptionsWitness($sql),
                null,
                2048,
            ),
            'postgresql.constraint.alter_role.valid_until' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterRoleStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_contains($sql, 'VALID UNTIL'),
                null,
                2048,
            ),
            'postgresql.constraint.create_role.name_and_options' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateRoleStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_starts_with($sql, 'CREATE ROLE '),
                null,
                2048,
            ),
            'postgresql.constraint.drop_role.name_list' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'DropRoleStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isDropRoleNameListWitness($sql),
                null,
                2048,
            ),
            'postgresql.constraint.grant_role.name_list' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'GrantRoleStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_starts_with($sql, 'GRANT '),
                null,
                2048,
            ),
            'postgresql.constraint.revoke_role.name_list' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'RevokeRoleStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_starts_with($sql, 'REVOKE '),
                null,
                2048,
            ),
            'postgresql.constraint.create_user.name_and_options' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateUserStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_starts_with($sql, 'CREATE USER '),
                null,
                2048,
            ),
            'postgresql.constraint.create_group.name_and_options' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateGroupStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_starts_with($sql, 'CREATE GROUP '),
                null,
                2048,
            ),
            'postgresql.constraint.alter_extension.content_target' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'AlterExtensionContentsStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_starts_with($sql, 'ALTER EXTENSION '),
                null,
                4096,
            ),
            'postgresql.constraint.grant.large_object_target' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'GrantStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_contains($sql, ' ON LARGE OBJECT '),
                null,
                4096,
            ),
            'postgresql.constraint.create_table.partition_strategy' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_contains($sql, ' PARTITION BY '),
                null,
                4096,
            ),
            'postgresql.constraint.create_table.partition_of' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreatePartitionOfStmt', seed: $seed, maxDepth: 8)),
                null,
                null,
                2048,
            ),
            'postgresql.constraint.create_table.temp_name_binding' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isTemporaryCreateTableWitness($sql),
                fn (string $sql, array $parameters): array => $this->extractTemporaryRelationProperties($sql, 'TABLE'),
                4096,
            ),
            'postgresql.constraint.create_table_as.temp_name_binding' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateAsStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isTemporaryCreateTableAsWitness($sql),
                fn (string $sql, array $parameters): array => $this->extractTemporaryRelationProperties($sql, 'TABLE'),
                4096,
            ),
            'postgresql.constraint.execute_create_table_as.temp_name_binding' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'ExecuteStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isTemporaryExecuteCreateTableAsWitness($sql),
                fn (string $sql, array $parameters): array => $this->extractTemporaryRelationProperties($sql, 'TABLE'),
                4096,
            ),
            'postgresql.constraint.create_sequence.temp_name_binding' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateSeqStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isTemporaryCreateSequenceWitness($sql),
                fn (string $sql, array $parameters): array => $this->extractTemporaryRelationProperties($sql, 'SEQUENCE'),
                4096,
            ),
            'postgresql.constraint.create_view.explicit_columns' => $this->generateExplicitViewWitness($runtime, $request),
            'postgresql.constraint.create_view.temp_name_binding' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'ViewStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isTemporaryCreateViewWitness($sql),
                fn (string $sql, array $parameters): array => $this->extractTemporaryCreateViewProperties($sql),
                4096,
            ),
            'postgresql.constraint.insert.explicit_columns' => $this->generateExplicitInsertWitness($runtime, $request),
            'postgresql.constraint.insert.conflict_update' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'insert_conflict_update_stmt', seed: $seed, maxDepth: 8)),
                null,
                null,
                2048,
            ),
            'postgresql.constraint.grant.parameter_target' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'GrantStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_contains($sql, ' ON PARAMETER '),
                null,
                4096,
            ),
            'postgresql.constraint.select.set_operation' => $this->generateSetOperationWitness($runtime, $request),
            'postgresql.constraint.select.values_clause' => $this->generateValuesClauseWitness($runtime, $request),
            'postgresql.constraint.text_search_template.define' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'DefineTextSearchTemplateStmt', seed: $seed, maxDepth: 8)),
            ),
            'postgresql.constraint.create_operator.definition' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'DefineOperatorStmt', seed: $seed, maxDepth: 8)),
                null,
                null,
                512,
            ),
            'postgresql.constraint.create_aggregate.definition' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'DefineAggregateStmt', seed: $seed, maxDepth: 8)),
                null,
                null,
                512,
            ),
            'postgresql.constraint.comment.type_reference' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CommentTypeReferenceStmt', seed: $seed, maxDepth: 8)),
                null,
                null,
                512,
            ),
            'postgresql.constraint.create_cast.type_reference' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateCastStmt', seed: $seed, maxDepth: 8)),
                null,
                null,
                512,
            ),
            'postgresql.constraint.drop_cast.type_reference' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'DropCastStmt', seed: $seed, maxDepth: 8)),
                null,
                null,
                512,
            ),
            'postgresql.constraint.create_assertion.check_expression' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateAssertionStmt', seed: $seed, maxDepth: 8)),
                null,
                null,
                512,
            ),
            'postgresql.constraint.create_routine.complete_definition' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateFunctionStmt', seed: $seed, maxDepth: 8)),
                fn (string $sql, array $parameters): bool => $this->isCreateRoutineCompleteDefinitionWitness($sql),
                null,
                2048,
            ),
            'postgresql.constraint.drop_type.object_name' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'DropStmt', seed: $seed, maxDepth: 8)),
                static fn (string $sql, array $parameters): bool => str_starts_with($sql, 'DROP TYPE') || str_starts_with($sql, 'DROP DOMAIN'),
                null,
                2048,
            ),
            'postgresql.lex.identifier.context' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => 'SELECT ' . $runtime->generate(new GenerationRequest(startRule: 'ColId', seed: $seed, maxDepth: 1)),
            ),
            default => throw new LogicException(sprintf('Unsupported family: %s', $request->familyId)),
        };
    }

    /**
     * @return list<FamilyDefinition>
     */
    protected function buildFamilies(): array
    {
        return [
            new FamilyDefinition('postgresql.statement.any', 'Any PostgreSQL statement through the default provider entry.', 'spec', ['stmtmulti']),
            new FamilyDefinition('postgresql.statement.select', 'SELECT statements through the provider surface.', 'spec', ['SelectStmt']),
            new FamilyDefinition('postgresql.statement.insert', 'INSERT statements through the provider surface.', 'spec', ['InsertStmt']),
            new FamilyDefinition('postgresql.statement.update', 'UPDATE statements through the provider surface.', 'spec', ['UpdateStmt']),
            new FamilyDefinition('postgresql.statement.delete', 'DELETE statements through the provider surface.', 'spec', ['DeleteStmt']),
            new FamilyDefinition('postgresql.constraint.distinct_on', 'SELECT statements that realize the DISTINCT ON family.', 'contract', ['SelectStmt', 'distinct_clause', 'safe_distinct_on_expr_list']),
            new FamilyDefinition('postgresql.constraint.alter_materialized_view', 'ALTER MATERIALIZED VIEW command family after TypedSplit refinement.', 'contract', ['AlterTableStmt', 'materialized_view_alter_table_cmd']),
            new FamilyDefinition('postgresql.constraint.alter_index.commands', 'ALTER INDEX command family after object-specific command and value restriction.', 'contract', ['AlterIndexStmt', 'index_alter_table_cmd']),
            new FamilyDefinition('postgresql.constraint.alter_view.commands', 'ALTER VIEW command family after object-specific command restriction.', 'contract', ['AlterTableStmt', 'view_alter_table_cmd']),
            new FamilyDefinition('postgresql.constraint.alter_domain.add', 'ALTER DOMAIN ADD constraint family after ExclusiveChoice refinement.', 'contract', ['AlterDomainStmt']),
            new FamilyDefinition('postgresql.constraint.create_table_as.explicit_columns', 'Explicit-column CREATE TABLE AS family with finite arity refinement.', 'contract', ['CreateAsStmt', 'ctas_select_stmt_1', 'ctas_select_stmt_8'], ['arity'], ['projection_arity', 'column_list_arity']),
            new FamilyDefinition('postgresql.constraint.alter_sequence', 'ALTER SEQUENCE family after object-specific command restriction.', 'contract', ['AlterSeqStmt']),
            new FamilyDefinition('postgresql.constraint.alter_statistics', 'ALTER STATISTICS family after value-domain restriction.', 'contract', ['AlterStatsStmt']),
            new FamilyDefinition('postgresql.constraint.alter_type.options', 'ALTER TYPE option family after safe option-name restriction.', 'contract', ['AlterTypeStmt', 'safe_alter_type_option']),
            new FamilyDefinition('postgresql.constraint.alter_routine.options', 'ALTER FUNCTION, PROCEDURE, and ROUTINE family after duplicate-option elimination.', 'contract', ['AlterFunctionStmt', 'safe_alter_routine_option']),
            new FamilyDefinition('postgresql.constraint.alter_database.options', 'ALTER DATABASE option family after option restriction.', 'contract', ['AlterDatabaseStmt', 'createdb_opt_item']),
            new FamilyDefinition('postgresql.constraint.alter_role.valid_until', 'ALTER ROLE VALID UNTIL family after timestamp-domain restriction.', 'contract', ['AlterRoleStmt']),
            new FamilyDefinition('postgresql.constraint.create_role.name_and_options', 'CREATE ROLE family after reserved-name and singleton-option restriction.', 'contract', ['CreateRoleStmt']),
            new FamilyDefinition('postgresql.constraint.drop_role.name_list', 'DROP ROLE, DROP USER, and DROP GROUP families after pseudo-role exclusion.', 'contract', ['DropRoleStmt']),
            new FamilyDefinition('postgresql.constraint.grant_role.name_list', 'GRANT role-membership family after pseudo-role exclusion on both sides.', 'contract', ['GrantRoleStmt']),
            new FamilyDefinition('postgresql.constraint.revoke_role.name_list', 'REVOKE role-membership family after pseudo-role exclusion on both sides.', 'contract', ['RevokeRoleStmt']),
            new FamilyDefinition('postgresql.constraint.create_user.name_and_options', 'CREATE USER family after reserved-name and singleton-option restriction.', 'contract', ['CreateUserStmt']),
            new FamilyDefinition('postgresql.constraint.create_group.name_and_options', 'CREATE GROUP family after reserved-name and singleton-option restriction.', 'contract', ['CreateGroupStmt']),
            new FamilyDefinition('postgresql.constraint.alter_extension.content_target', 'ALTER EXTENSION content family after extension-member object restriction.', 'contract', ['AlterExtensionContentsStmt']),
            new FamilyDefinition('postgresql.constraint.grant.large_object_target', 'GRANT ON LARGE OBJECT family after OID-domain restriction.', 'contract', ['GrantStmt', 'privilege_target']),
            new FamilyDefinition('postgresql.constraint.create_table.partition_strategy', 'CREATE TABLE partition family after keyword-strategy restriction.', 'contract', ['CreateStmt', 'PartitionSpec']),
            new FamilyDefinition('postgresql.constraint.create_table.partition_of', 'CREATE TABLE PARTITION OF family after temp-modifier restriction.', 'contract', ['CreatePartitionOfStmt']),
            new FamilyDefinition('postgresql.constraint.create_table.temp_name_binding', 'CREATE TABLE family after temporary-relation name binding.', 'contract', ['CreateStmt'], ['schema_qualified'], ['schema_qualified']),
            new FamilyDefinition('postgresql.constraint.create_table_as.temp_name_binding', 'CREATE TABLE AS family after temporary-relation name binding.', 'contract', ['CreateAsStmt'], ['schema_qualified'], ['schema_qualified']),
            new FamilyDefinition('postgresql.constraint.execute_create_table_as.temp_name_binding', 'CREATE TABLE AS EXECUTE family after temporary-relation name binding.', 'contract', ['ExecuteStmt'], ['schema_qualified'], ['schema_qualified']),
            new FamilyDefinition('postgresql.constraint.create_sequence.temp_name_binding', 'CREATE SEQUENCE family after temporary-relation name binding.', 'contract', ['CreateSeqStmt'], ['schema_qualified'], ['schema_qualified']),
            new FamilyDefinition('postgresql.constraint.create_view.explicit_columns', 'CREATE VIEW explicit-column family after finite-arity and temp-modifier restriction.', 'contract', ['ViewStmt'], ['arity'], ['projection_arity', 'column_list_arity']),
            new FamilyDefinition('postgresql.constraint.create_view.temp_name_binding', 'CREATE VIEW family after temporary-relation name binding.', 'contract', ['ViewStmt'], ['schema_qualified'], ['schema_qualified']),
            new FamilyDefinition('postgresql.constraint.insert.explicit_columns', 'INSERT explicit-column family after finite-arity select restriction.', 'contract', ['InsertStmt', 'insert_rest'], ['arity'], ['projection_arity', 'column_list_arity']),
            new FamilyDefinition('postgresql.constraint.insert.conflict_update', 'INSERT ON CONFLICT DO UPDATE family after inference-spec restriction.', 'contract', ['insert_conflict_update_stmt', 'opt_on_conflict']),
            new FamilyDefinition('postgresql.constraint.grant.parameter_target', 'GRANT ON PARAMETER family after configuration-parameter-name restriction.', 'contract', ['GrantStmt', 'privilege_target']),
            new FamilyDefinition('postgresql.constraint.select.set_operation', 'SELECT set-operation family after finite-arity operand restriction.', 'contract', ['simple_select', 'set_operation_select_stmt', 'setop_select_stmt_1', 'setop_select_stmt_8'], ['arity'], ['left_projection_arity', 'right_projection_arity']),
            new FamilyDefinition('postgresql.constraint.select.values_clause', 'VALUES clause family after constant-expression and finite-arity row restriction.', 'contract', ['select_values_clause', 'select_values_clause_1', 'select_values_clause_8'], ['arity'], ['row_arity']),
            new FamilyDefinition('postgresql.constraint.text_search_template.define', 'CREATE TEXT SEARCH TEMPLATE family after definition restriction.', 'contract', ['DefineTextSearchTemplateStmt']),
            new FamilyDefinition('postgresql.constraint.create_operator.definition', 'CREATE OPERATOR family after required-procedure and safe-type restriction.', 'contract', ['DefineOperatorStmt', 'safe_operator_definition']),
            new FamilyDefinition('postgresql.constraint.create_aggregate.definition', 'CREATE AGGREGATE family after required SFUNC and STYPE restriction.', 'contract', ['DefineAggregateStmt', 'safe_aggregate_definition']),
            new FamilyDefinition('postgresql.constraint.comment.type_reference', 'COMMENT ON TYPE/DOMAIN/CAST/TRANSFORM family after object-name and safe-type restriction.', 'contract', ['CommentTypeReferenceStmt', 'CommentStmt']),
            new FamilyDefinition('postgresql.constraint.create_cast.type_reference', 'CREATE CAST family after safe-type restriction.', 'contract', ['CreateCastStmt']),
            new FamilyDefinition('postgresql.constraint.drop_cast.type_reference', 'DROP CAST family after safe-type restriction.', 'contract', ['DropCastStmt']),
            new FamilyDefinition('postgresql.constraint.create_assertion.check_expression', 'CREATE ASSERTION family after safe boolean expression restriction.', 'contract', ['CreateAssertionStmt', 'safe_assertion_check_expr']),
            new FamilyDefinition('postgresql.constraint.create_routine.complete_definition', 'CREATE FUNCTION and PROCEDURE family after complete-definition restriction.', 'contract', ['CreateFunctionStmt']),
            new FamilyDefinition('postgresql.constraint.drop_type.object_name', 'DROP TYPE and DROP DOMAIN family after object-name restriction.', 'contract', ['DropStmt']),
            new FamilyDefinition('postgresql.lex.identifier.context', 'Identifier rendering inside a distinguishing SELECT context.', 'spec', ['ColId']),
        ];
    }

    private function generateExplicitCtasWitness(Runtime $runtime, FamilyRequest $request): SqlWitness
    {
        $arity = $request->parameters['arity'] ?? null;
        if (!is_int($arity) && !is_string($arity)) {
            throw new LogicException('arity parameter must be present for explicit CTAS witnesses.');
        }

        $expectedArity = (int) $arity;
        if ($expectedArity < 1 || $expectedArity > 8) {
            throw new LogicException('arity parameter must be between 1 and 8.');
        }

        return $this->searchWitness(
            $runtime,
            $request->familyId,
            ['arity' => $expectedArity],
            fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'CreateAsStmt', seed: $seed, maxDepth: 8)),
            fn (string $sql, array $parameters): bool => $this->isExplicitCtasArityWitness($sql, (int) $parameters['arity']),
            fn (string $sql, array $parameters): array => $this->extractExplicitCtasProperties($sql),
            4096,
        );
    }

    private function generateExplicitViewWitness(Runtime $runtime, FamilyRequest $request): SqlWitness
    {
        $arity = $request->parameters['arity'] ?? null;
        if (!is_int($arity) && !is_string($arity)) {
            throw new LogicException('arity parameter must be present for explicit VIEW witnesses.');
        }

        $expectedArity = (int) $arity;
        if ($expectedArity < 1 || $expectedArity > 8) {
            throw new LogicException('arity parameter must be between 1 and 8.');
        }

        return $this->searchWitness(
            $runtime,
            $request->familyId,
            ['arity' => $expectedArity],
            fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'ViewStmt', seed: $seed, maxDepth: 8)),
            fn (string $sql, array $parameters): bool => $this->isExplicitViewArityWitness($sql, (int) $parameters['arity']),
            fn (string $sql, array $parameters): array => $this->extractExplicitViewProperties($sql),
            4096,
        );
    }

    private function generateExplicitInsertWitness(Runtime $runtime, FamilyRequest $request): SqlWitness
    {
        $arity = $request->parameters['arity'] ?? null;
        if (!is_int($arity) && !is_string($arity)) {
            throw new LogicException('arity parameter must be present for explicit INSERT witnesses.');
        }

        $expectedArity = (int) $arity;
        if ($expectedArity < 1 || $expectedArity > 8) {
            throw new LogicException('arity parameter must be between 1 and 8.');
        }

        return $this->searchWitness(
            $runtime,
            $request->familyId,
            ['arity' => $expectedArity],
            fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'InsertStmt', seed: $seed, maxDepth: 8)),
            fn (string $sql, array $parameters): bool => $this->isExplicitInsertArityWitness($sql, (int) $parameters['arity']),
            fn (string $sql, array $parameters): array => $this->extractExplicitInsertProperties($sql),
            4096,
        );
    }

    private function generateSetOperationWitness(Runtime $runtime, FamilyRequest $request): SqlWitness
    {
        $arity = $request->parameters['arity'] ?? null;
        if (!is_int($arity) && !is_string($arity)) {
            throw new LogicException('arity parameter must be present for set-operation witnesses.');
        }

        $expectedArity = (int) $arity;
        if ($expectedArity < 1 || $expectedArity > 8) {
            throw new LogicException('arity parameter must be between 1 and 8.');
        }

        return $this->searchWitness(
            $runtime,
            $request->familyId,
            ['arity' => $expectedArity],
            fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: sprintf('setop_select_stmt_%d', (int) $parameters['arity']), seed: $seed, maxDepth: 8)),
            fn (string $sql, array $parameters): bool => $this->isSetOperationArityWitness($sql, (int) $parameters['arity']),
            fn (string $sql, array $parameters): array => $this->extractSetOperationProperties($sql),
            64,
        );
    }

    private function generateValuesClauseWitness(Runtime $runtime, FamilyRequest $request): SqlWitness
    {
        $arity = $request->parameters['arity'] ?? null;
        if (!is_int($arity) && !is_string($arity)) {
            throw new LogicException('arity parameter must be present for VALUES clause witnesses.');
        }

        $expectedArity = (int) $arity;
        if ($expectedArity < 1 || $expectedArity > 8) {
            throw new LogicException('arity parameter must be between 1 and 8.');
        }

        return $this->searchWitness(
            $runtime,
            $request->familyId,
            ['arity' => $expectedArity],
            fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: sprintf('select_values_clause_%d', (int) $parameters['arity']), seed: $seed, maxDepth: 8)),
            fn (string $sql, array $parameters): bool => $this->isValuesClauseArityWitness($sql, (int) $parameters['arity']),
            fn (string $sql, array $parameters): array => $this->extractValuesClauseProperties($sql),
            64,
        );
    }

    private function isExplicitCtasArityWitness(string $sql, int $arity): bool
    {
        $properties = $this->extractExplicitCtasProperties($sql);

        return ($properties['projection_arity'] ?? null) === $arity
            && ($properties['column_list_arity'] ?? null) === $arity;
    }

    private function isExplicitViewArityWitness(string $sql, int $arity): bool
    {
        $properties = $this->extractExplicitViewProperties($sql);

        return ($properties['projection_arity'] ?? null) === $arity
            && ($properties['column_list_arity'] ?? null) === $arity;
    }

    private function isExplicitInsertArityWitness(string $sql, int $arity): bool
    {
        $properties = $this->extractExplicitInsertProperties($sql);

        return ($properties['projection_arity'] ?? null) === $arity
            && ($properties['column_list_arity'] ?? null) === $arity;
    }

    private function isSetOperationArityWitness(string $sql, int $arity): bool
    {
        $properties = $this->extractSetOperationProperties($sql);

        return ($properties['left_projection_arity'] ?? null) === $arity
            && ($properties['right_projection_arity'] ?? null) === $arity;
    }

    private function isValuesClauseArityWitness(string $sql, int $arity): bool
    {
        $properties = $this->extractValuesClauseProperties($sql);

        return ($properties['row_arity'] ?? null) === $arity;
    }

    private function isTemporaryCreateTableWitness(string $sql): bool
    {
        return $this->startsWithTemporaryCreate($sql, 'TABLE')
            && !str_contains($sql, ' AS ')
            && !str_contains($sql, ' PARTITION OF ');
    }

    private function isTemporaryCreateTableAsWitness(string $sql): bool
    {
        return $this->startsWithTemporaryCreate($sql, 'TABLE')
            && str_contains($sql, ' AS ')
            && !str_contains($sql, ' AS EXECUTE ');
    }

    private function isTemporaryExecuteCreateTableAsWitness(string $sql): bool
    {
        return $this->startsWithTemporaryCreate($sql, 'TABLE')
            && str_contains($sql, ' AS EXECUTE ');
    }

    private function isTemporaryCreateSequenceWitness(string $sql): bool
    {
        return $this->startsWithTemporaryCreate($sql, 'SEQUENCE');
    }

    private function isTemporaryCreateViewWitness(string $sql): bool
    {
        return preg_match(
            '/^CREATE(?:\s+OR\s+REPLACE)?\s+(?:(?:LOCAL|GLOBAL)\s+TEMP(?:ORARY)?|TEMP(?:ORARY)?)\s+(?:RECURSIVE\s+)?VIEW\b/',
            $sql,
        ) === 1;
    }

    private function isAlterMaterializedViewWitness(string $sql): bool
    {
        return str_starts_with($sql, 'ALTER MATERIALIZED VIEW')
            || str_starts_with($sql, 'ALTER MATERIALIZED VIEW IF EXISTS');
    }

    private function isAlterViewCommandWitness(string $sql): bool
    {
        return str_starts_with($sql, 'ALTER VIEW ')
            || str_starts_with($sql, 'ALTER VIEW IF EXISTS ');
    }

    private function isAlterDomainAddWitness(string $sql): bool
    {
        return str_contains($sql, ' ADD ');
    }

    private function isAlterRoutineOptionsWitness(string $sql): bool
    {
        return str_starts_with($sql, 'ALTER FUNCTION ')
            || str_starts_with($sql, 'ALTER PROCEDURE ')
            || str_starts_with($sql, 'ALTER ROUTINE ');
    }

    private function isAlterDatabaseOptionsWitness(string $sql): bool
    {
        return str_contains($sql, 'CONNECTION LIMIT')
            || str_contains($sql, 'ALLOW_CONNECTIONS')
            || str_contains($sql, 'IS_TEMPLATE');
    }

    private function isDropRoleNameListWitness(string $sql): bool
    {
        return str_starts_with($sql, 'DROP ROLE ')
            || str_starts_with($sql, 'DROP ROLE IF EXISTS ')
            || str_starts_with($sql, 'DROP USER ')
            || str_starts_with($sql, 'DROP USER IF EXISTS ')
            || str_starts_with($sql, 'DROP GROUP ')
            || str_starts_with($sql, 'DROP GROUP IF EXISTS ');
    }

    private function isCreateRoutineCompleteDefinitionWitness(string $sql): bool
    {
        return str_starts_with($sql, 'CREATE FUNCTION')
            || str_starts_with($sql, 'CREATE OR REPLACE FUNCTION')
            || str_starts_with($sql, 'CREATE PROCEDURE')
            || str_starts_with($sql, 'CREATE OR REPLACE PROCEDURE');
    }

    /**
     * @return array<string, scalar>
     */
    private function extractExplicitCtasProperties(string $sql): array
    {
        if (preg_match('/TABLE(?: IF_P NOT EXISTS| IF NOT EXISTS)?\s+[^\s(]+\(([^)]*)\).*?AS\s+SELECT\s+(.+?)(?:\s+WITH(?:OUT)?\s+DATA)?$/', $sql, $matches) !== 1) {
            return [];
        }

        return [
            'column_list_arity' => $this->topLevelCsvArity($matches[1]),
            'projection_arity' => $this->topLevelCsvArity($matches[2]),
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function extractExplicitViewProperties(string $sql): array
    {
        if (preg_match('/VIEW\s+[^\s(]+\(([^)]*)\)\s+(?:WITH\s*\([^)]*\)\s+)?AS\s+SELECT\s+(.+?)(?:\s+WITH(?:\s+CASCADED|\s+LOCAL)?\s+CHECK\s+OPTION)?$/', $sql, $matches) !== 1) {
            return [];
        }

        return [
            'column_list_arity' => $this->topLevelCsvArity($matches[1]),
            'projection_arity' => $this->topLevelCsvArity($matches[2]),
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function extractExplicitInsertProperties(string $sql): array
    {
        if (preg_match('/INSERT\s+INTO\s+[^\s(]+(?:\s+AS\s+[^\s(]+)?\(([^)]*)\)\s+(?:OVERRIDING\s+(?:USER|SYSTEM)\s+VALUE\s+)?SELECT\s+(.+?)(?:\s+ON\s+CONFLICT.*)?(?:\s+RETURNING.*)?$/', $sql, $matches) !== 1) {
            return [];
        }

        return [
            'column_list_arity' => $this->topLevelCsvArity($matches[1]),
            'projection_arity' => $this->topLevelCsvArity($matches[2]),
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function extractSetOperationProperties(string $sql): array
    {
        [$leftOperand, $rightOperand] = $this->splitTopLevelSetOperation($sql);
        if ($leftOperand === null || $rightOperand === null) {
            return [];
        }

        $leftProjection = $this->selectProjectionSegment($leftOperand);
        $rightProjection = $this->selectProjectionSegment($rightOperand);
        if ($leftProjection === null || $rightProjection === null) {
            return [];
        }

        return [
            'left_projection_arity' => $this->topLevelCsvArity($leftProjection),
            'right_projection_arity' => $this->topLevelCsvArity($rightProjection),
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function extractValuesClauseProperties(string $sql): array
    {
        if (!str_starts_with($sql, 'VALUES(')) {
            return [];
        }

        $depth = 0;
        $singleQuoted = false;
        $doubleQuoted = false;
        $length = strlen($sql);
        $start = 7;

        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === "'" && !$doubleQuoted) {
                $singleQuoted = !$singleQuoted;
                continue;
            }

            if ($char === '"' && !$singleQuoted) {
                $doubleQuoted = !$doubleQuoted;
                continue;
            }

            if ($singleQuoted || $doubleQuoted) {
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                if ($depth === 0) {
                    return [
                        'row_arity' => $this->topLevelCsvArity(substr($sql, $start, $i - $start)),
                    ];
                }

                $depth--;
            }
        }

        return [];
    }

    /**
     * @return array<string, scalar>
     */
    private function extractTemporaryRelationProperties(string $sql, string $objectKeyword): array
    {
        $pattern = sprintf(
            '/^CREATE\s+(?:(?:LOCAL|GLOBAL)\s+TEMP(?:ORARY)?|TEMP(?:ORARY)?)\s+%s(?:\s+IF\s+NOT\s+EXISTS)?\s+([^\s(]+)/',
            preg_quote($objectKeyword, '/'),
        );
        if (preg_match($pattern, $sql, $matches) !== 1) {
            return [];
        }

        return [
            'schema_qualified' => str_contains($matches[1], '.'),
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function extractTemporaryCreateViewProperties(string $sql): array
    {
        if (preg_match(
            '/^CREATE(?:\s+OR\s+REPLACE)?\s+(?:(?:LOCAL|GLOBAL)\s+TEMP(?:ORARY)?|TEMP(?:ORARY)?)\s+(?:RECURSIVE\s+)?VIEW\s+([^\s(]+)/',
            $sql,
            $matches,
        ) !== 1) {
            return [];
        }

        return [
            'schema_qualified' => str_contains($matches[1], '.'),
        ];
    }

    private function startsWithTemporaryCreate(string $sql, string $objectKeyword): bool
    {
        return preg_match(
            sprintf(
                '/^CREATE\s+(?:(?:LOCAL|GLOBAL)\s+TEMP(?:ORARY)?|TEMP(?:ORARY)?)\s+%s\b/',
                preg_quote($objectKeyword, '/'),
            ),
            $sql,
        ) === 1;
    }

    private function topLevelCsvArity(string $segment): int
    {
        $depth = 0;
        $singleQuoted = false;
        $doubleQuoted = false;
        $count = 1;
        $length = strlen($segment);

        for ($i = 0; $i < $length; $i++) {
            $char = $segment[$i];

            if ($char === "'" && !$doubleQuoted) {
                $singleQuoted = !$singleQuoted;
                continue;
            }

            if ($char === '"' && !$singleQuoted) {
                $doubleQuoted = !$doubleQuoted;
                continue;
            }

            if ($singleQuoted || $doubleQuoted) {
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $count++;
            }
        }

        return $segment === '' ? 0 : $count;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitTopLevelSetOperation(string $sql): array
    {
        $depth = 0;
        $singleQuoted = false;
        $doubleQuoted = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === "'" && !$doubleQuoted) {
                $singleQuoted = !$singleQuoted;
                continue;
            }

            if ($char === '"' && !$singleQuoted) {
                $doubleQuoted = !$doubleQuoted;
                continue;
            }

            if ($singleQuoted || $doubleQuoted) {
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            foreach ([' UNION ', ' INTERSECT ', ' EXCEPT '] as $operator) {
                if (substr($sql, $i, strlen($operator)) !== $operator) {
                    continue;
                }

                $left = trim(substr($sql, 0, $i));
                $remainder = trim(substr($sql, $i + strlen($operator)));

                if (str_starts_with($remainder, 'ALL ')) {
                    $remainder = trim(substr($remainder, 4));
                } elseif (str_starts_with($remainder, 'DISTINCT ')) {
                    $remainder = trim(substr($remainder, 9));
                }

                return [$left, $remainder];
            }
        }

        return [null, null];
    }

    private function selectProjectionSegment(string $operand): ?string
    {
        $trimmed = trim($operand);
        if (str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')')) {
            $trimmed = trim(substr($trimmed, 1, -1));
        }

        if (!str_starts_with($trimmed, 'SELECT ')) {
            return null;
        }

        return substr($trimmed, strlen('SELECT '));
    }
}
