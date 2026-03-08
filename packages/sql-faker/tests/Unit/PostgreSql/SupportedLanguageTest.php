<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

require_once dirname(__DIR__, 2) . '/Support/SupportedLanguagePool.php';

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFakerTestSupport\SupportedLanguagePool;
use SqlFaker\Contract\FamilyDefinition;
use SqlFaker\Contract\FamilyRequest;
use SqlFaker\Contract\GrammarAlternativeSnapshot;
use SqlFaker\Contract\GrammarRuleSnapshot;
use SqlFaker\Contract\GrammarSnapshot;
use SqlFaker\Contract\GrammarSnapshotBuilder;
use SqlFaker\Contract\GrammarSymbolSnapshot;
use SqlFaker\Contract\SqlWitness;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\PostgreSql\SqlGenerator;
use SqlFaker\PostgreSql\SupportedLanguage;
use SqlFaker\PostgreSqlProvider;

#[CoversClass(SupportedLanguage::class)]
#[UsesClass(FamilyDefinition::class)]
#[UsesClass(FamilyRequest::class)]
#[UsesClass(GrammarAlternativeSnapshot::class)]
#[UsesClass(GrammarRuleSnapshot::class)]
#[UsesClass(GrammarSnapshot::class)]
#[UsesClass(GrammarSnapshotBuilder::class)]
#[UsesClass(GrammarSymbolSnapshot::class)]
#[UsesClass(SqlWitness::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(SqlGenerator::class)]
#[UsesClass(PostgreSqlProvider::class)]
final class SupportedLanguageTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        gc_collect_cycles();
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        SupportedLanguagePool::clear();

        parent::tearDownAfterClass();
    }

    public function testExposesPostgreSqlSupportedLanguageContract(): void
    {
        $language = SupportedLanguagePool::postgresql();
        $witness = $language->generateWitness(new FamilyRequest('postgresql.statement.select'));

        self::assertSame('postgresql', $language->dialect());
        self::assertContains('stmtmulti', $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['SelectStmt', 'distinct_clause', 'safe_distinct_on_expr_list'],
            $language->family('postgresql.constraint.distinct_on')->anchorRules,
        );
        self::assertNotSame('', $witness->sql);
    }

    #[DataProvider('providerDeterministicWitnessSql')]
    public function testGenerateWitnessUsesDeterministicCanonicalSqlForStableFamilies(
        string $familyId,
        int $expectedSeed,
        string $expectedSql,
    ): void {
        $language = SupportedLanguagePool::postgresql();
        $witness = $language->generateWitness(new FamilyRequest($familyId));

        self::assertSame($expectedSeed, $witness->seed);
        self::assertSame($expectedSql, $witness->sql);
    }

    /**
     * @param array<string, scalar> $parameters
     * @param array<string, scalar> $expectedParameters
     * @param array<string, int> $expectedProperties
     */
    #[DataProvider('providerDeterministicWitnessWithProperties')]
    public function testGenerateWitnessUsesDeterministicCanonicalSqlForPropertyBearingFamilies(
        string $familyId,
        array $parameters,
        array $expectedParameters,
        int $expectedSeed,
        array $expectedProperties,
        string $expectedSql,
    ): void {
        $language = SupportedLanguagePool::postgresql();
        $witness = $language->generateWitness(new FamilyRequest($familyId, $parameters));

        self::assertSame($expectedSeed, $witness->seed);
        self::assertSame($expectedParameters, $witness->parameters);
        self::assertSame($expectedProperties, $witness->properties);
        self::assertSame($expectedSql, $witness->sql);
    }

    #[DataProvider('providerTemporaryRelationFamily')]
    public function testTemporaryRelationFamiliesBindObjectNamesToUnqualifiedRelations(string $familyId): void
    {
        $language = SupportedLanguagePool::postgresql();
        $witness = $language->generateWitness(new FamilyRequest($familyId));

        self::assertSame([], $language->family($familyId)->parameterNames, $familyId);
        self::assertFalse($witness->properties['schema_qualified'], $familyId);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function providerTemporaryRelationFamily(): iterable
    {
        yield 'create table' => ['postgresql.constraint.create_table.temp_name_binding'];
        yield 'create table as' => ['postgresql.constraint.create_table_as.temp_name_binding'];
        yield 'execute create table as' => ['postgresql.constraint.execute_create_table_as.temp_name_binding'];
        yield 'create sequence' => ['postgresql.constraint.create_sequence.temp_name_binding'];
        yield 'create view' => ['postgresql.constraint.create_view.temp_name_binding'];
    }

    /**
     * @return iterable<string, array{0: string, 1: int, 2: string}>
     */
    public static function providerDeterministicWitnessSql(): iterable
    {
        yield 'statement any' => ['postgresql.statement.any', 1, 'ALTER MATERIALIZED VIEW ALL IN TABLESPACE _i0 SET TABLESPACE _i1'];
        yield 'statement select' => ['postgresql.statement.select', 1, '(TABLE ONLY _i0._i1)'];
        yield 'statement insert' => ['postgresql.statement.insert', 1, 'INSERT INTO _i0 OVERRIDING SYSTEM VALUE SELECT _i1'];
        yield 'statement update' => ['postgresql.statement.update', 1, 'UPDATE _i0._i1 SET _i2 = _i3'];
        yield 'statement delete' => ['postgresql.statement.delete', 1, 'DELETE FROM _i0._i1'];
        yield 'distinct on' => ['postgresql.constraint.distinct_on', 61, '(SELECT DISTINCT ON(_i0) _i1)'];
        yield 'alter materialized view' => ['postgresql.constraint.alter_materialized_view', 8, 'ALTER MATERIALIZED VIEW _i0 SET TABLESPACE _i1'];
        yield 'alter index commands' => ['postgresql.constraint.alter_index.commands', 1, 'ALTER INDEX IF EXISTS _i0._i1 RESET(_i2)'];
        yield 'alter view commands' => ['postgresql.constraint.alter_view.commands', 19, 'ALTER VIEW _i0 RESET(_i1)'];
        yield 'alter domain add' => ['postgresql.constraint.alter_domain.add', 4, 'ALTER DOMAIN _i0 ADD CONSTRAINT _i1 CHECK(_i2 IS DOCUMENT)'];
        yield 'alter sequence' => ['postgresql.constraint.alter_sequence', 7, 'ALTER SEQUENCE IF EXISTS _i0 OWNER TO SESSION_USER'];
        yield 'alter statistics' => ['postgresql.constraint.alter_statistics', 1, 'ALTER STATISTICS IF EXISTS _i0._i1 SET STATISTICS 100'];
        yield 'alter type options' => ['postgresql.constraint.alter_type.options', 1, 'ALTER TYPE _i0._i1 SET(RECEIVE = NONE, RECEIVE = NONE, RECEIVE = NONE)'];
        yield 'alter routine options' => ['postgresql.constraint.alter_routine.options', 1, 'ALTER PROCEDURE _i0() EXTERNAL SECURITY INVOKER'];
        yield 'alter database options' => ['postgresql.constraint.alter_database.options', 1, 'ALTER DATABASE _i0 CONNECTION LIMIT DEFAULT'];
        yield 'alter role valid until' => ['postgresql.constraint.alter_role.valid_until', 5, "ALTER USER CURRENT_USER WITH VALID UNTIL '2000-01-01 00:00:00+00'"];
        yield 'create role name and options' => ['postgresql.constraint.create_role.name_and_options', 1, 'CREATE ROLE _i0 IN GROUP _i1, _i2'];
        yield 'drop role name list' => ['postgresql.constraint.drop_role.name_list', 1, 'DROP ROLE IF EXISTS _i0, _i1'];
        yield 'grant role name list' => ['postgresql.constraint.grant_role.name_list', 1, 'GRANT _i0, _i1 TO _i2'];
        yield 'revoke role name list' => ['postgresql.constraint.revoke_role.name_list', 1, 'REVOKE _i0, _i1 FROM _i2'];
        yield 'create user name and options' => ['postgresql.constraint.create_user.name_and_options', 1, 'CREATE USER _i0 IN GROUP _i1, _i2'];
        yield 'create group name and options' => ['postgresql.constraint.create_group.name_and_options', 1, 'CREATE GROUP _i0 IN GROUP _i1, _i2'];
        yield 'insert conflict update' => ['postgresql.constraint.insert.conflict_update', 1, 'INSERT INTO _i0 OVERRIDING SYSTEM VALUE SELECT _i1 ON CONFLICT(_i2) DO UPDATE SET _i3 = 2143362695'];
        yield 'partition strategy' => ['postgresql.constraint.create_table.partition_strategy', 16, 'CREATE LOCAL TEMPORARY TABLE _i0() PARTITION BY RANGE(_i1)'];
        yield 'grant parameter target' => ['postgresql.constraint.grant.parameter_target', 15, 'GRANT ALL PRIVILEGES(_i0) ON PARAMETER search_path TO _i1'];
        yield 'identifier context' => ['postgresql.lex.identifier.context', 1, 'SELECT _i0'];
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, scalar>, 2: array<string, scalar>, 3: int, 4: array<string, int>, 5: string}>
     */
    public static function providerDeterministicWitnessWithProperties(): iterable
    {
        yield 'explicit columns arity 1' => [
            'postgresql.constraint.create_table_as.explicit_columns',
            ['arity' => 1],
            ['arity' => 1],
            10,
            ['column_list_arity' => 1, 'projection_arity' => 1],
            'CREATE LOCAL TEMP TABLE _i0(_i1) AS SELECT 1425548446',
        ];
        yield 'explicit columns arity 3 as string' => [
            'postgresql.constraint.create_table_as.explicit_columns',
            ['arity' => '3'],
            ['arity' => 3],
            1,
            ['column_list_arity' => 3, 'projection_arity' => 3],
            'CREATE GLOBAL TEMP TABLE _i0(_i1, _i2, _i3) AS SELECT 2143362695, 630311760, 1013994433',
        ];
    }
}
