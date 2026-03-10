<?php

declare(strict_types=1);

namespace Spec\MySql;

use LogicException;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Runtime;
use Spec\Specification\AbstractDialectSpecification;
use Spec\Subject\FamilyDefinition;
use Spec\Subject\FamilyRequest;
use Spec\Subject\SqlWitness;

/**
 * Spec-local MySQL witness generation and family metadata.
 */
final class Specification extends AbstractDialectSpecification
{
    public function entryRules(): array
    {
        return ['simple_statement_or_begin'];
    }

    /**
     * Generates one MySQL witness that satisfies the requested family constraints.
     */
    public function generateWitness(Runtime $runtime, FamilyRequest $request): SqlWitness
    {
        $this->assertFamilyParameters($request);

        return match ($request->familyId) {
            'mysql.statement.any' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(seed: $seed, maxDepth: 8))),
            'mysql.statement.select' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'select_stmt', seed: $seed, maxDepth: 8))),
            'mysql.statement.insert' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'insert_stmt', seed: $seed, maxDepth: 8))),
            'mysql.statement.update' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'update_stmt', seed: $seed, maxDepth: 8))),
            'mysql.statement.delete' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'delete_stmt', seed: $seed, maxDepth: 8))),
            'mysql.constraint.transaction.commit' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'commit', seed: $seed, maxDepth: 2))),
            'mysql.constraint.transaction.rollback' => $this->searchWitness($runtime, $request->familyId, $request->parameters, fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'rollback', seed: $seed, maxDepth: 2))),
            'mysql.constraint.table_value_constructor' => $this->generateTableValueConstructorWitness($runtime, $request),
            'mysql.constraint.set_system_variable' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'set_system_variable_stmt', seed: $seed, maxDepth: 4)),
            ),
            'mysql.constraint.create_srs' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'create_srs_stmt', seed: $seed, maxDepth: 6)),
            ),
            'mysql.constraint.signal_sqlstate' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'signal_sqlstate_stmt', seed: $seed, maxDepth: 4)),
            ),
            'mysql.constraint.show_warnings.limit' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'show_warnings_stmt', seed: $seed, maxDepth: 6)),
                static fn (string $sql, array $parameters): bool => str_contains($sql, 'LIMIT'),
            ),
            'mysql.constraint.alter_database.encryption' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'alter_database_encryption_stmt', seed: $seed, maxDepth: 6)),
            ),
            'mysql.constraint.change_replication_source' => $this->generateChangeReplicationSourceWitness($runtime, $request),
            'mysql.lex.identifier.context' => $this->searchWitness(
                $runtime,
                $request->familyId,
                $request->parameters,
                fn (Runtime $runtime, array $parameters, int $seed): string => 'SELECT ' . $runtime->generate(new GenerationRequest(startRule: 'ident', seed: $seed, maxDepth: 1)),
            ),
            'mysql.lex.identifier.freshness' => $this->generateIdentifierFreshnessWitness($runtime, $request),
            default => throw new LogicException(sprintf('Unsupported family: %s', $request->familyId)),
        };
    }

    /**
     * @return list<FamilyDefinition>
     */
    protected function buildFamilies(): array
    {
        return [
            new FamilyDefinition('mysql.statement.any', 'Any MySQL statement through the default entry family.', 'spec', ['simple_statement_or_begin']),
            new FamilyDefinition('mysql.statement.select', 'SELECT statements through the provider surface.', 'spec', ['select_stmt']),
            new FamilyDefinition('mysql.statement.insert', 'INSERT statements through the provider surface.', 'spec', ['insert_stmt']),
            new FamilyDefinition('mysql.statement.update', 'UPDATE statements through the provider surface.', 'spec', ['update_stmt']),
            new FamilyDefinition('mysql.statement.delete', 'DELETE statements through the provider surface.', 'spec', ['delete_stmt']),
            new FamilyDefinition('mysql.constraint.transaction.commit', 'Canonical COMMIT combinations after refinement.', 'contract', ['commit']),
            new FamilyDefinition('mysql.constraint.transaction.rollback', 'Canonical ROLLBACK combinations after refinement.', 'contract', ['rollback']),
            new FamilyDefinition('mysql.constraint.table_value_constructor', 'Top-level VALUES table constructors after finite-arity row refinement.', 'contract', ['table_value_constructor', 'table_value_constructor_1', 'table_value_constructor_8'], ['arity'], ['row_arity']),
            new FamilyDefinition('mysql.constraint.set_system_variable', 'SET system variable statements after canonical variable-name refinement.', 'contract', ['set_system_variable_stmt']),
            new FamilyDefinition('mysql.constraint.create_srs', 'CREATE SPATIAL REFERENCE SYSTEM after mandatory attribute and definition refinement.', 'contract', ['create_srs_stmt']),
            new FamilyDefinition('mysql.constraint.signal_sqlstate', 'SIGNAL SQLSTATE statements after canonical SQLSTATE refinement.', 'contract', ['signal_sqlstate_stmt']),
            new FamilyDefinition('mysql.constraint.show_warnings.limit', 'SHOW WARNINGS statements that include a LIMIT clause.', 'contract', ['show_warnings_stmt']),
            new FamilyDefinition('mysql.constraint.alter_database.encryption', 'ALTER DATABASE statements that include ENCRYPTION options.', 'contract', ['alter_database_encryption_stmt']),
            new FamilyDefinition('mysql.lex.identifier.context', 'Identifier rendering inside a distinguishing SELECT context.', 'spec', ['ident']),
            new FamilyDefinition('mysql.lex.identifier.freshness', 'Statement-local canonical identifier rendering with fresh values.', 'contract', [], [], ['first_identifier', 'second_identifier']),
            new FamilyDefinition(
                'mysql.constraint.change_replication_source',
                'CHANGE REPLICATION SOURCE statements after scalar option refinement.',
                'contract',
                ['change_replication_stmt', 'change'],
            ),
        ];
    }

    private function generateTableValueConstructorWitness(Runtime $runtime, FamilyRequest $request): SqlWitness
    {
        $arity = $request->parameters['arity'] ?? null;
        if (!is_int($arity) && !is_string($arity)) {
            throw new LogicException('arity parameter must be present for table value constructor witnesses.');
        }

        $expectedArity = (int) $arity;
        if ($expectedArity < 1 || $expectedArity > 8) {
            throw new LogicException('arity parameter must be between 1 and 8.');
        }

        return $this->searchWitness(
            $runtime,
            $request->familyId,
            ['arity' => $expectedArity],
            fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: sprintf('table_value_constructor_%d', $expectedArity), seed: $seed, maxDepth: 6)),
            null,
            fn (string $sql, array $parameters): array => ['row_arity' => $this->extractTableValueArity($sql)],
        );
    }

    private function extractTableValueArity(string $sql): int
    {
        if (preg_match('/^VALUES\s+ROW\(([^)]*)\)/', $sql, $matches) !== 1) {
            return 0;
        }

        return substr_count($matches[1], ',') + 1;
    }

    private function generateChangeReplicationSourceWitness(Runtime $runtime, FamilyRequest $request): SqlWitness
    {
        return $this->searchWitness(
            $runtime,
            $request->familyId,
            $request->parameters,
            fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: $this->changeReplicationSourceRule($runtime), seed: $seed, maxDepth: 6)),
            static fn (string $sql, array $parameters): bool => str_starts_with($sql, 'CHANGE REPLICATION SOURCE TO '),
        );
    }

    private function changeReplicationSourceRule(Runtime $runtime): string
    {
        if ($runtime->snapshot()->rule('change_replication_stmt') !== null) {
            return 'change_replication_stmt';
        }

        if ($runtime->snapshot()->rule('change') !== null) {
            return 'change';
        }

        throw new LogicException('CHANGE REPLICATION SOURCE is not supported by this grammar.');
    }

    private function generateIdentifierFreshnessWitness(Runtime $runtime, FamilyRequest $request): SqlWitness
    {
        return $this->searchWitness(
            $runtime,
            $request->familyId,
            $request->parameters,
            fn (Runtime $runtime, array $parameters, int $seed): string => $runtime->generate(new GenerationRequest(startRule: 'select_stmt', seed: $seed, maxDepth: 8)),
            fn (string $sql, array $parameters): bool => $this->isIdentifierFreshnessWitness($sql),
            fn (string $sql, array $parameters): array => $this->extractIdentifierFreshnessProperties($sql),
        );
    }

    private function isIdentifierFreshnessWitness(string $sql): bool
    {
        $properties = $this->extractIdentifierFreshnessProperties($sql);

        return $properties['first_identifier'] !== ''
            && $properties['second_identifier'] !== ''
            && $properties['first_identifier'] !== $properties['second_identifier'];
    }

    /**
     * @return array{first_identifier: string, second_identifier: string}
     */
    private function extractIdentifierFreshnessProperties(string $sql): array
    {
        if (preg_match('/^SELECT\s+([A-Za-z_][A-Za-z0-9_$]*)\s*,\s*([A-Za-z_][A-Za-z0-9_$]*)\b/', $sql, $matches) !== 1) {
            return [
                'first_identifier' => '',
                'second_identifier' => '',
            ];
        }

        return [
            'first_identifier' => $matches[1],
            'second_identifier' => $matches[2],
        ];
    }
}
