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
     * @param array<string, scalar> $expectedProperties
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
     * @param array<string, scalar> $parameters
     */
    #[DataProvider('providerInvalidArityWitnessRequest')]
    public function testGenerateWitnessRejectsMissingAndOutOfRangeArityForParameterizedFamilies(
        string $familyId,
        array $parameters,
        string $expectedMessage,
    ): void {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($expectedMessage);
        $language = SupportedLanguagePool::postgresql();
        $language->generateWitness(new FamilyRequest($familyId, $parameters));
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
     * @return iterable<string, array{0: string, 1: array<string, scalar>, 2: string}>
     */
    public static function providerInvalidArityWitnessRequest(): iterable
    {
        yield 'ctas missing arity' => [
            'postgresql.constraint.create_table_as.explicit_columns',
            [],
            'Missing required parameter arity for family postgresql.constraint.create_table_as.explicit_columns.',
        ];
        yield 'ctas arity below range' => [
            'postgresql.constraint.create_table_as.explicit_columns',
            ['arity' => 0],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'ctas arity above range' => [
            'postgresql.constraint.create_table_as.explicit_columns',
            ['arity' => 9],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'view missing arity' => [
            'postgresql.constraint.create_view.explicit_columns',
            [],
            'Missing required parameter arity for family postgresql.constraint.create_view.explicit_columns.',
        ];
        yield 'view arity below range' => [
            'postgresql.constraint.create_view.explicit_columns',
            ['arity' => 0],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'view arity above range' => [
            'postgresql.constraint.create_view.explicit_columns',
            ['arity' => 9],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'insert missing arity' => [
            'postgresql.constraint.insert.explicit_columns',
            [],
            'Missing required parameter arity for family postgresql.constraint.insert.explicit_columns.',
        ];
        yield 'insert arity below range' => [
            'postgresql.constraint.insert.explicit_columns',
            ['arity' => 0],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'insert arity above range' => [
            'postgresql.constraint.insert.explicit_columns',
            ['arity' => 9],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'set operation missing arity' => [
            'postgresql.constraint.select.set_operation',
            [],
            'Missing required parameter arity for family postgresql.constraint.select.set_operation.',
        ];
        yield 'set operation arity below range' => [
            'postgresql.constraint.select.set_operation',
            ['arity' => 0],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'set operation arity above range' => [
            'postgresql.constraint.select.set_operation',
            ['arity' => 9],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'values clause missing arity' => [
            'postgresql.constraint.select.values_clause',
            [],
            'Missing required parameter arity for family postgresql.constraint.select.values_clause.',
        ];
        yield 'values clause arity below range' => [
            'postgresql.constraint.select.values_clause',
            ['arity' => 0],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'values clause arity above range' => [
            'postgresql.constraint.select.values_clause',
            ['arity' => 9],
            'arity parameter must be between 1 and 8.',
        ];
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
        yield 'alter extension content target' => ['postgresql.constraint.alter_extension.content_target', 1, 'ALTER EXTENSION _i0 ADD OPERATOR _i1.% (_i2, _i3)'];
        yield 'grant large object target' => ['postgresql.constraint.grant.large_object_target', 2, 'GRANT ALL PRIVILEGES ON LARGE OBJECT 1024 TO _i0, _i1'];
        yield 'insert conflict update' => ['postgresql.constraint.insert.conflict_update', 1, 'INSERT INTO _i0 OVERRIDING SYSTEM VALUE SELECT _i1 ON CONFLICT(_i2) DO UPDATE SET _i3 = 2143362695'];
        yield 'partition strategy' => ['postgresql.constraint.create_table.partition_strategy', 16, 'CREATE LOCAL TEMPORARY TABLE _i0() PARTITION BY RANGE(_i1)'];
        yield 'partition of' => ['postgresql.constraint.create_table.partition_of', 1, 'CREATE TABLE IF NOT EXISTS _i0 PARTITION OF _i1._i2 DEFAULT'];
        yield 'grant parameter target' => ['postgresql.constraint.grant.parameter_target', 15, 'GRANT ALL PRIVILEGES(_i0) ON PARAMETER search_path TO _i1'];
        yield 'text search template define' => ['postgresql.constraint.text_search_template.define', 1, 'CREATE TEXT SEARCH TEMPLATE _i0._i1(INIT = _i2, LEXIZE = _i3)'];
        yield 'create operator definition' => ['postgresql.constraint.create_operator.definition', 1, 'CREATE OPERATOR _i0.- (PROCEDURE = _i1, LEFTARG = INTEGER, RIGHTARG = NONE)'];
        yield 'create aggregate definition' => ['postgresql.constraint.create_aggregate.definition', 1, 'CREATE AGGREGATE _i0(_i1, _i2, _i3 ORDER BY _i4) (SFUNC = _i5, STYPE = INTEGER)'];
        yield 'comment type reference' => ['postgresql.constraint.comment.type_reference', 1, 'COMMENT ON DOMAIN _i0._i1 IS NULL'];
        yield 'create cast type reference' => ['postgresql.constraint.create_cast.type_reference', 1, 'CREATE CAST(BYTEA AS TEXT) WITHOUT FUNCTION AS IMPLICIT'];
        yield 'drop cast type reference' => ['postgresql.constraint.drop_cast.type_reference', 1, 'DROP CAST(BYTEA AS TEXT)'];
        yield 'create assertion check expression' => ['postgresql.constraint.create_assertion.check_expression', 1, 'CREATE ASSERTION _i0._i1 CHECK(2143362695 != 630311760) DEFERRABLE'];
        yield 'create routine complete definition' => ['postgresql.constraint.create_routine.complete_definition', 1, 'CREATE FUNCTION _i0() RETURNS INT RETURN 1'];
        yield 'drop type object name' => ['postgresql.constraint.drop_type.object_name', 11, 'DROP DOMAIN IF EXISTS _i0, _i1._i2'];
        yield 'identifier context' => ['postgresql.lex.identifier.context', 1, 'SELECT _i0'];
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, scalar>, 2: array<string, scalar>, 3: int, 4: array<string, scalar>, 5: string}>
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
        yield 'temp table name binding' => [
            'postgresql.constraint.create_table.temp_name_binding',
            [],
            [],
            1,
            ['schema_qualified' => false],
            'CREATE GLOBAL TEMP TABLE _i0 OF _i1._i2',
        ];
        yield 'temp table as name binding' => [
            'postgresql.constraint.create_table_as.temp_name_binding',
            [],
            [],
            1,
            ['schema_qualified' => false],
            'CREATE GLOBAL TEMP TABLE _i0(_i1, _i2, _i3) AS SELECT 2143362695, 630311760, 1013994433',
        ];
        yield 'execute temp table as name binding' => [
            'postgresql.constraint.execute_create_table_as.temp_name_binding',
            [],
            [],
            10,
            ['schema_qualified' => false],
            'CREATE LOCAL TEMP TABLE _i0 AS EXECUTE _i1',
        ];
        yield 'temp sequence name binding' => [
            'postgresql.constraint.create_sequence.temp_name_binding',
            [],
            [],
            1,
            ['schema_qualified' => false],
            'CREATE GLOBAL TEMP SEQUENCE _i0',
        ];
        yield 'view explicit columns arity 1' => [
            'postgresql.constraint.create_view.explicit_columns',
            ['arity' => 1],
            ['arity' => 1],
            6,
            ['column_list_arity' => 1, 'projection_arity' => 1],
            'CREATE OR REPLACE VIEW _i0._i1(_i2) AS SELECT 1589607787',
        ];
        yield 'view explicit columns arity 3 as string' => [
            'postgresql.constraint.create_view.explicit_columns',
            ['arity' => '3'],
            ['arity' => 3],
            8,
            ['column_list_arity' => 3, 'projection_arity' => 3],
            'CREATE OR REPLACE GLOBAL TEMPORARY RECURSIVE VIEW _i0(_i1, _i2, _i3) AS SELECT 1359190869, 999560553, 1813982912',
        ];
        yield 'temp view name binding' => [
            'postgresql.constraint.create_view.temp_name_binding',
            [],
            [],
            1,
            ['schema_qualified' => false],
            'CREATE GLOBAL TEMP VIEW _i0(_i1, _i2, _i3, _i4, _i5, _i6, _i7) AS SELECT 2143362695, 630311760, 1013994433, 396591249, 1703301250, 799981517, 1666063944',
        ];
        yield 'insert explicit columns arity 1' => [
            'postgresql.constraint.insert.explicit_columns',
            ['arity' => 1],
            ['arity' => 1],
            33,
            ['column_list_arity' => 1, 'projection_arity' => 1],
            'INSERT INTO _i0(_i1) SELECT 472197316',
        ];
        yield 'insert explicit columns arity 3 as string' => [
            'postgresql.constraint.insert.explicit_columns',
            ['arity' => '3'],
            ['arity' => 3],
            6,
            ['column_list_arity' => 3, 'projection_arity' => 3],
            'INSERT INTO _i0 AS _i1(_i2, _i3, _i4) SELECT 1589607787, 462381905, 2083182912',
        ];
        yield 'set operation arity 1' => [
            'postgresql.constraint.select.set_operation',
            ['arity' => 1],
            ['arity' => 1],
            1,
            ['left_projection_arity' => 1, 'right_projection_arity' => 1],
            '(SELECT 30311759.32 AS _i0) INTERSECT SELECT 396591249',
        ];
        yield 'set operation arity 3 as string' => [
            'postgresql.constraint.select.set_operation',
            ['arity' => '3'],
            ['arity' => 3],
            1,
            ['left_projection_arity' => 3, 'right_projection_arity' => 3],
            '(SELECT 30311759.32 AS _i0, 396591249, 1703301250) INTERSECT SELECT 799981517, 1666063944, 1484172014',
        ];
        yield 'values clause arity 1' => [
            'postgresql.constraint.select.values_clause',
            ['arity' => 1],
            ['arity' => 1],
            1,
            ['row_arity' => 1],
            'VALUES(NULL), (2143362695)',
        ];
        yield 'values clause arity 3 as string' => [
            'postgresql.constraint.select.values_clause',
            ['arity' => '3'],
            ['arity' => 3],
            1,
            ['row_arity' => 3],
            'VALUES(NULL, NULL, 2143362695), (630311760, 1013994433, 396591249)',
        ];
    }
}
