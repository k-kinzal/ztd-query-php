<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\FamilyDefinition;
use SqlFaker\Contract\FamilyRequest;
use SqlFaker\Contract\GrammarAlternativeSnapshot;
use SqlFaker\Contract\GrammarRuleSnapshot;
use SqlFaker\Contract\GrammarSnapshot;
use SqlFaker\Contract\GrammarSnapshotBuilder;
use SqlFaker\Contract\GrammarSymbolSnapshot;
use SqlFaker\Contract\SqlWitness;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Sqlite\SqlGenerator;
use SqlFaker\Sqlite\SupportedLanguage;
use SqlFaker\SqliteProvider;

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
#[UsesClass(SqliteProvider::class)]
final class SupportedLanguageTest extends TestCase
{
    public function testExposesSqliteSupportedLanguageContract(): void
    {
        $language = new SupportedLanguage();
        $witness = $language->generateWitness(new FamilyRequest(
            'sqlite.constraint.select.values_clause',
            ['arity' => 3],
        ));

        self::assertSame('sqlite', $language->dialect());
        self::assertContains('cmd', $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['attach_stmt', 'safe_attach_filename_expr', 'safe_attach_schema_expr'],
            $language->family('sqlite.constraint.attach.expression')->anchorRules,
        );
        self::assertSame(3, $witness->properties['row_arity']);
        self::assertStringStartsWith('VALUES(', $witness->sql);
    }

    public function testGeneratesStarRequiresFromWitness(): void
    {
        $language = new SupportedLanguage();
        $witness = $language->generateWitness(new FamilyRequest('sqlite.constraint.select.star_requires_from'));

        self::assertStringStartsWith('SELECT ', $witness->sql);
    }

    public function testGeneratesTemporaryObjectNameBindingWitness(): void
    {
        $language = new SupportedLanguage();
        $witness = $language->generateWitness(new FamilyRequest('sqlite.constraint.temporary_object_name_binding'));

        self::assertMatchesRegularExpression(
            '/^CREATE\s+TEMP(?:ORARY)?\s+(?:TABLE|VIEW|TRIGGER)\s+(?:IF\s+NOT\s+EXISTS\s+)?[^\s.(]+/',
            $witness->sql,
        );
    }

    #[DataProvider('providerDeterministicWitnessSql')]
    public function testGenerateWitnessUsesDeterministicCanonicalSqlForStableFamilies(
        string $familyId,
        string $expectedSql,
    ): void {
        $language = new SupportedLanguage();

        $witness = $language->generateWitness(new FamilyRequest($familyId));

        self::assertSame(1, $witness->seed);
        self::assertSame($expectedSql, $witness->sql);
    }

    /**
     * @param array<string, int> $expectedProperties
     */
    #[DataProvider('providerDeterministicArityWitness')]
    public function testGenerateWitnessUsesDeterministicCanonicalSqlForArityFamilies(
        string $familyId,
        int|string $arity,
        array $expectedProperties,
        string $expectedSql,
    ): void {
        $language = new SupportedLanguage();

        $witness = $language->generateWitness(new FamilyRequest($familyId, ['arity' => $arity]));

        self::assertSame(1, $witness->seed);
        self::assertSame(['arity' => (int) $arity], $witness->parameters);
        self::assertSame($expectedProperties, $witness->properties);
        self::assertSame($expectedSql, $witness->sql);
    }

    /**
     * @param array<string, scalar> $parameters
     */
    #[DataProvider('providerInvalidArityRequest')]
    public function testGenerateWitnessRejectsMissingAndOutOfRangeArities(
        string $familyId,
        array $parameters,
        string $expectedMessage,
    ): void {
        $language = new SupportedLanguage();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($expectedMessage);
        $language->generateWitness(new FamilyRequest($familyId, $parameters));
    }

    public function testExtractValuesClausePropertiesCountsTopLevelExpressionsAroundQuotesAndNesting(): void
    {
        $language = new SupportedLanguage();
        $method = (new \ReflectionClass($language))->getMethod('extractValuesClauseProperties');

        self::assertSame(
            ['row_arity' => 3],
            $method->invoke($language, 'VALUES("a,b", 2, 3)'),
        );
        self::assertSame(
            ['row_arity' => 3],
            $method->invoke($language, 'VALUES(1, (2,3), "x,y")'),
        );
        self::assertSame([], $method->invoke($language, 'SELECT 1, 2, 3'));
    }

    public function testExtractSetOperationPropertiesCountsSelectAndValuesOperands(): void
    {
        $language = new SupportedLanguage();
        $method = (new \ReflectionClass($language))->getMethod('extractSetOperationProperties');

        self::assertSame(
            ['left_projection_arity' => 2, 'right_projection_arity' => 2],
            $method->invoke($language, 'SELECT 1, 2 UNION SELECT 3, 4'),
        );
        self::assertSame(
            ['left_projection_arity' => 2, 'right_projection_arity' => 2],
            $method->invoke($language, 'VALUES(1, 2) UNION ALL SELECT 3, 4'),
        );
    }

    public function testSplitTopLevelSetOperationIgnoresQuotedAndNestedOperators(): void
    {
        $language = new SupportedLanguage();
        $method = (new \ReflectionClass($language))->getMethod('splitTopLevelSetOperation');

        self::assertSame(
            ['SELECT "UNION"', 'SELECT 2'],
            $method->invoke($language, 'SELECT "UNION" UNION SELECT 2'),
        );
        self::assertSame(
            ['SELECT (SELECT 1 UNION SELECT 2)', 'SELECT 3'],
            $method->invoke($language, 'SELECT (SELECT 1 UNION SELECT 2) UNION SELECT 3'),
        );
        self::assertSame(
            ['VALUES(1)', 'VALUES(2)'],
            $method->invoke($language, 'VALUES(1) UNION ALL VALUES(2)'),
        );
    }

    public function testSelectProjectionSegmentSkipsModifiersAndStopsAtTopLevelDelimiters(): void
    {
        $language = new SupportedLanguage();
        $method = (new \ReflectionClass($language))->getMethod('selectProjectionSegment');

        self::assertSame('1, 2', $method->invoke($language, 'SELECT DISTINCT 1, 2 FROM t'));
        self::assertSame('1, 2', $method->invoke($language, 'SELECT ALL 1, 2 WHERE 1'));
        self::assertSame('"a,b", (1,2)', $method->invoke($language, 'SELECT "a,b", (1,2) ORDER BY 1'));
    }

    public function testTopLevelCsvArityIgnoresQuotedAndNestedCommas(): void
    {
        $language = new SupportedLanguage();
        $method = (new \ReflectionClass($language))->getMethod('topLevelCsvArity');

        self::assertSame(3, $method->invoke($language, '"a,b", 2, 3'));
        self::assertSame(3, $method->invoke($language, '1, (2,3), "x,y"'));
        self::assertSame(1, $method->invoke($language, '1'));
    }

    public function testTemporaryObjectNameBindingRejectsQualifiedNames(): void
    {
        $language = new SupportedLanguage();
        $method = (new \ReflectionClass($language))->getMethod('isTemporaryObjectNameBindingWitness');

        self::assertTrue($method->invoke($language, 'CREATE TEMP TABLE foo AS SELECT 1'));
        self::assertFalse($method->invoke($language, 'CREATE TEMP VIEW main.foo AS SELECT 1'));
        self::assertTrue($method->invoke($language, 'CREATE TEMP TRIGGER foo AFTER INSERT ON bar BEGIN SELECT 1; END'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function providerDeterministicWitnessSql(): iterable
    {
        yield 'statement any' => ['sqlite.statement.any', 'ALTER TABLE _i0 RENAME COLUMN _i1 TO _i2'];
        yield 'statement select' => ['sqlite.statement.select', 'WITH RECURSIVE _i0(_i1) AS(SELECT NULL), _i2 AS(SELECT NULL) SELECT NULL'];
        yield 'statement insert' => ['sqlite.statement.insert', 'WITH RECURSIVE _i0(_i1) AS(SELECT NULL) INSERT INTO _i2 DEFAULT VALUES'];
        yield 'statement update' => ['sqlite.statement.update', 'WITH RECURSIVE _i0(_i1) AS(SELECT NULL) UPDATE _i2 SET _i3 = NULL'];
        yield 'statement delete' => ['sqlite.statement.delete', 'WITH RECURSIVE _i0(_i1) AS(SELECT NULL) DELETE FROM _i2'];
        yield 'statement create table' => ['sqlite.statement.create_table', 'CREATE TEMP TABLE _i0 AS SELECT NULL'];
        yield 'statement alter table' => ['sqlite.statement.alter_table', 'ALTER TABLE _i0 ADD COLUMN _i1'];
        yield 'statement drop table' => ['sqlite.statement.drop_table', 'DROP TABLE _i0'];
        yield 'attach expression' => ['sqlite.constraint.attach.expression', "ATTACH 'JTlWtA' AS _i0 KEY _i1._i2"];
        yield 'detach expression' => ['sqlite.constraint.detach.expression', 'DETACH _i0'];
        yield 'vacuum into expression' => ['sqlite.constraint.vacuum.into_expression', 'VACUUM _i0'];
        yield 'identifier context' => ['sqlite.lex.identifier.context', 'SELECT _i0'];
        yield 'star requires from' => ['sqlite.constraint.select.star_requires_from', 'SELECT *, NULL'];
        yield 'temporary object binding' => ['sqlite.constraint.temporary_object_name_binding', 'CREATE TEMP TABLE _i0 AS SELECT NULL'];
    }

    /**
     * @return iterable<string, array{0: string, 1: int|string, 2: array<string, int>, 3: string}>
     */
    public static function providerDeterministicArityWitness(): iterable
    {
        yield 'values clause arity 1' => [
            'sqlite.constraint.select.values_clause',
            1,
            ['row_arity' => 1],
            "VALUES('JTlWtA'), (NULL)",
        ];
        yield 'values clause arity 3 as string' => [
            'sqlite.constraint.select.values_clause',
            '3',
            ['row_arity' => 3],
            "VALUES('JTlWtA', NULL, NULL), (NULL, NULL, NULL)",
        ];
        yield 'values clause arity 8' => [
            'sqlite.constraint.select.values_clause',
            8,
            ['row_arity' => 8],
            "VALUES('JTlWtA', NULL, NULL, NULL, NULL, NULL, NULL, NULL), (NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)",
        ];
        yield 'set operation arity 1' => [
            'sqlite.constraint.select.set_operation',
            1,
            ['left_projection_arity' => 1, 'right_projection_arity' => 1],
            'VALUES(NULL), (NULL), (NULL) UNION SELECT NULL UNION SELECT NULL UNION SELECT NULL',
        ];
        yield 'set operation arity 3 as string' => [
            'sqlite.constraint.select.set_operation',
            '3',
            ['left_projection_arity' => 3, 'right_projection_arity' => 3],
            'VALUES(NULL, NULL, NULL), (NULL, NULL, NULL), (NULL, NULL, NULL) UNION SELECT NULL, NULL, NULL UNION SELECT NULL, NULL, NULL UNION SELECT NULL, NULL, NULL',
        ];
        yield 'set operation arity 8' => [
            'sqlite.constraint.select.set_operation',
            8,
            ['left_projection_arity' => 8, 'right_projection_arity' => 8],
            'VALUES(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL), (NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL), (NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL) UNION SELECT NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL UNION SELECT NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL UNION SELECT NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL',
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, scalar>, 2: string}>
     */
    public static function providerInvalidArityRequest(): iterable
    {
        yield 'values clause missing arity' => [
            'sqlite.constraint.select.values_clause',
            [],
            'Missing required parameter arity for family sqlite.constraint.select.values_clause.',
        ];
        yield 'values clause arity below range' => [
            'sqlite.constraint.select.values_clause',
            ['arity' => 0],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'set operation arity above range' => [
            'sqlite.constraint.select.set_operation',
            ['arity' => 9],
            'arity parameter must be between 1 and 8.',
        ];
    }
}
