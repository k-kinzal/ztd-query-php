<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Factory;
use Faker\Generator as FakerGenerator;
use SqlFaker\Contract\AbstractSupportedLanguage;
use SqlFaker\Contract\FamilyDefinition;
use SqlFaker\Contract\FamilyRequest;
use SqlFaker\Contract\GrammarSnapshot;
use SqlFaker\Contract\GrammarSnapshotBuilder;
use SqlFaker\Contract\SqlWitness;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySqlProvider;

/**
 * Public supported-language contract for MySQL.
 */
final class SupportedLanguage extends AbstractSupportedLanguage
{
    private FakerGenerator $faker;
    private MySqlProvider $provider;
    private Grammar $grammar;
    private SqlGenerator $generator;
    private GrammarSnapshotBuilder $snapshotBuilder;

    public function __construct(?string $version = null)
    {
        $this->faker = Factory::create();
        $this->provider = new MySqlProvider($this->faker, $version);
        $this->grammar = Grammar::load($version);
        $this->generator = new SqlGenerator($this->grammar, $this->faker, $this->provider);
        $this->snapshotBuilder = new GrammarSnapshotBuilder();
    }

    /**
     * Returns the SQL dialect served by this subject.
     */
    public function dialect(): string
    {
        return 'mysql';
    }

    /**
     * Generates one MySQL witness that satisfies the requested family constraints.
     */
    public function generateWitness(FamilyRequest $request): SqlWitness
    {
        $this->assertFamilyParameters($request);

        return match ($request->familyId) {
            'mysql.statement.any' => $this->searchWitness($request->familyId, $request->parameters, fn (): string => $this->provider->sql(null, 8)),
            'mysql.statement.select' => $this->searchWitness($request->familyId, $request->parameters, fn (): string => $this->provider->selectStatement(8)),
            'mysql.statement.insert' => $this->searchWitness($request->familyId, $request->parameters, fn (): string => $this->provider->insertStatement(8)),
            'mysql.statement.update' => $this->searchWitness($request->familyId, $request->parameters, fn (): string => $this->provider->updateStatement(8)),
            'mysql.statement.delete' => $this->searchWitness($request->familyId, $request->parameters, fn (): string => $this->provider->deleteStatement(8)),
            'mysql.constraint.transaction.commit' => $this->searchWitness($request->familyId, $request->parameters, fn (): string => $this->generator->generate('commit', 2)),
            'mysql.constraint.transaction.rollback' => $this->searchWitness($request->familyId, $request->parameters, fn (): string => $this->generator->generate('rollback', 2)),
            'mysql.constraint.table_value_constructor' => $this->generateTableValueConstructorWitness($request),
            'mysql.constraint.set_system_variable' => $this->searchWitness(
                $request->familyId,
                $request->parameters,
                fn (): string => $this->generator->generate('set_system_variable_stmt', 4),
            ),
            'mysql.constraint.create_srs' => $this->searchWitness(
                $request->familyId,
                $request->parameters,
                fn (): string => $this->generator->generate('create_srs_stmt', 6),
            ),
            'mysql.constraint.signal_sqlstate' => $this->searchWitness(
                $request->familyId,
                $request->parameters,
                fn (): string => $this->generator->generate('signal_sqlstate_stmt', 4),
            ),
            'mysql.constraint.show_warnings.limit' => $this->searchWitness(
                $request->familyId,
                $request->parameters,
                fn (): string => $this->generator->generate('show_warnings_stmt', 6),
                static fn (string $sql): bool => str_contains($sql, 'LIMIT'),
            ),
            'mysql.constraint.alter_database.encryption' => $this->searchWitness(
                $request->familyId,
                $request->parameters,
                fn (): string => $this->generator->generate('alter_database_encryption_stmt', 6),
            ),
            'mysql.constraint.change_replication_source' => $this->generateChangeReplicationSourceWitness($request),
            'mysql.lex.identifier.context' => $this->searchWitness(
                $request->familyId,
                $request->parameters,
                fn (): string => 'SELECT ' . $this->provider->identifier(1),
            ),
            'mysql.lex.identifier.freshness' => $this->generateIdentifierFreshnessWitness($request),
            default => throw new \LogicException(sprintf('Unsupported family: %s', $request->familyId)),
        };
    }

    /**
     * @return list<FamilyDefinition>
     */
    protected function buildFamilies(): array
    {
        return [
            new FamilyDefinition('mysql.statement.any', 'Any MySQL statement through the default entry family.', 'spec', ['simple_statement_or_begin']),
            new FamilyDefinition('mysql.statement.select', 'SELECT statements through the provider surface.', 'spec', [StatementType::Select->value]),
            new FamilyDefinition('mysql.statement.insert', 'INSERT statements through the provider surface.', 'spec', [StatementType::Insert->value]),
            new FamilyDefinition('mysql.statement.update', 'UPDATE statements through the provider surface.', 'spec', [StatementType::Update->value]),
            new FamilyDefinition('mysql.statement.delete', 'DELETE statements through the provider surface.', 'spec', [StatementType::Delete->value]),
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
            ...($this->supportsChangeReplicationSource()
                ? [new FamilyDefinition(
                    'mysql.constraint.change_replication_source',
                    'CHANGE REPLICATION SOURCE statements after scalar option refinement.',
                    'contract',
                    [$this->changeReplicationSourceRule()],
                )]
                : []),
        ];
    }

    protected function buildGrammarSnapshot(): GrammarSnapshot
    {
        return $this->snapshotBuilder->build(
            $this->dialect(),
            $this->generator->compiledGrammar(),
            ['simple_statement_or_begin'],
            $this->familyCatalog(),
            \SqlFaker\MySql\Grammar\NonTerminal::class,
        );
    }

    protected function seed(int $seed): void
    {
        $this->faker->seed($seed);
    }

    private function generateTableValueConstructorWitness(FamilyRequest $request): SqlWitness
    {
        $arity = $request->parameters['arity'] ?? null;
        if (!is_int($arity) && !is_string($arity)) {
            throw new \LogicException('arity parameter must be present for table value constructor witnesses.');
        }

        $expectedArity = (int) $arity;
        if ($expectedArity < 1 || $expectedArity > 8) {
            throw new \LogicException('arity parameter must be between 1 and 8.');
        }

        return $this->searchWitness(
            $request->familyId,
            ['arity' => $expectedArity],
            fn (): string => $this->generator->generate(sprintf('table_value_constructor_%d', $expectedArity), 6),
            null,
            fn (string $sql): array => ['row_arity' => $this->extractTableValueArity($sql)],
        );
    }

    private function extractTableValueArity(string $sql): int
    {
        if (preg_match('/^VALUES\s+ROW\(([^)]*)\)/', $sql, $matches) !== 1) {
            return 0;
        }

        return substr_count($matches[1], ',') + 1;
    }

    private function generateChangeReplicationSourceWitness(FamilyRequest $request): SqlWitness
    {
        return $this->searchWitness(
            $request->familyId,
            $request->parameters,
            fn (): string => $this->generator->generate($this->changeReplicationSourceRule(), 6),
            static fn (string $sql): bool => str_starts_with($sql, 'CHANGE REPLICATION SOURCE TO '),
        );
    }

    private function supportsChangeReplicationSource(): bool
    {
        return isset($this->grammar->ruleMap['change_replication_stmt'])
            || isset($this->grammar->ruleMap['change_replication_source']);
    }

    private function changeReplicationSourceRule(): string
    {
        if (isset($this->grammar->ruleMap['change_replication_stmt'])) {
            return 'change_replication_stmt';
        }

        if (isset($this->grammar->ruleMap['change'])) {
            return 'change';
        }

        throw new \LogicException('CHANGE REPLICATION SOURCE is not supported by this grammar.');
    }

    private function generateIdentifierFreshnessWitness(FamilyRequest $request): SqlWitness
    {
        return $this->searchWitness(
            $request->familyId,
            $request->parameters,
            function (): string {
                $generator = new SqlGenerator(new Grammar('stmt', [
                    'stmt' => new ProductionRule('stmt', [
                        new Production([
                            new Terminal('SELECT_SYM'),
                            new Terminal('IDENT'),
                            new Terminal(','),
                            new Terminal('IDENT'),
                        ]),
                    ]),
                ]), $this->faker, $this->provider);

                return $generator->generate('stmt', 2);
            },
            null,
            function (string $sql): array {
                if (preg_match('/^SELECT\s+([^,]+),\s+(.+)$/', $sql, $matches) !== 1) {
                    return [
                        'first_identifier' => '',
                        'second_identifier' => '',
                    ];
                }

                return [
                    'first_identifier' => trim($matches[1]),
                    'second_identifier' => trim($matches[2]),
                ];
            },
        );
    }
}
