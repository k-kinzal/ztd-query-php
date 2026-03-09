<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

require_once dirname(__DIR__, 2) . '/Support/SupportedLanguagePool.php';

use LogicException;
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
use SqlFaker\MySql\SqlGenerator;
use SqlFaker\MySql\SupportedLanguage;
use SqlFaker\MySqlProvider;

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
#[UsesClass(MySqlProvider::class)]
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

    public function testExposesMySqlSupportedLanguageContract(): void
    {
        $language = SupportedLanguagePool::mysql('mysql-8.0.44');
        $witness = $language->generateWitness(new FamilyRequest(
            'mysql.constraint.table_value_constructor',
            ['arity' => 3],
        ));

        self::assertSame('mysql', $language->dialect());
        self::assertSame(['simple_statement_or_begin'], $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['simple_statement_or_begin'],
            $language->family('mysql.statement.any')->anchorRules,
        );
        self::assertSame(3, $witness->properties['row_arity']);
        self::assertStringStartsWith('VALUES ROW(', $witness->sql);
    }

    #[DataProvider('providerDeterministicWitnessSql')]
    public function testGenerateWitnessUsesDeterministicCanonicalSqlForStableFamilies(
        string $familyId,
        string $expectedSql,
    ): void {
        $language = SupportedLanguagePool::mysql('mysql-8.0.44');
        $witness = $language->generateWitness(new FamilyRequest($familyId));

        self::assertSame(1, $witness->seed);
        self::assertSame($expectedSql, $witness->sql);
    }

    public function testGenerateWitnessRejectsUnknownParametersBeforeSearching(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown parameters for family mysql.statement.select: unexpected');
        $language = SupportedLanguagePool::mysql('mysql-8.0.44');
        $language->generateWitness(new FamilyRequest('mysql.statement.select', ['unexpected' => 1]));
    }

    public function testGenerateWitnessRejectsUnknownFamiliesBeforeDispatch(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown family: mysql.unknown.family');
        $language = SupportedLanguagePool::mysql('mysql-8.0.44');
        $language->generateWitness(new FamilyRequest('mysql.unknown.family'));
    }

    /**
     * @param array<string, int> $expectedProperties
     */
    #[DataProvider('providerDeterministicTableValueConstructorWitness')]
    public function testGenerateWitnessUsesDeterministicCanonicalSqlForTableValueConstructor(
        int|string $arity,
        array $expectedProperties,
        string $expectedSql,
    ): void {
        $language = SupportedLanguagePool::mysql('mysql-8.0.44');
        $witness = $language->generateWitness(new FamilyRequest('mysql.constraint.table_value_constructor', ['arity' => $arity]));

        self::assertSame(1, $witness->seed);
        self::assertSame(['arity' => (int) $arity], $witness->parameters);
        self::assertSame($expectedProperties, $witness->properties);
        self::assertSame($expectedSql, $witness->sql);
    }

    /**
     * @param array<string, scalar> $parameters
     */
    #[DataProvider('providerInvalidTableValueConstructorRequest')]
    public function testGenerateWitnessRejectsMissingAndOutOfRangeTableValueConstructorArities(
        array $parameters,
        string $expectedMessage,
    ): void {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedMessage);
        $language = SupportedLanguagePool::mysql('mysql-8.0.44');
        $language->generateWitness(new FamilyRequest('mysql.constraint.table_value_constructor', $parameters));
    }

    public function testGenerateWitnessUsesDeterministicIdentifierFreshnessWitness(): void
    {
        $language = SupportedLanguagePool::mysql('mysql-8.0.44');
        $witness = $language->generateWitness(new FamilyRequest('mysql.lex.identifier.freshness'));

        self::assertSame(1, $witness->seed);
        self::assertSame('SELECT _i0, _i1', $witness->sql);
        self::assertSame([
            'first_identifier' => '_i0',
            'second_identifier' => '_i1',
        ], $witness->properties);
    }

    #[DataProvider('providerExtractTableValueArity')]
    public function testExtractTableValueArityCountsValuesRows(string $sql, int $expectedArity): void
    {
        $language = SupportedLanguagePool::mysql('mysql-8.0.44');
        $method = (new \ReflectionClass($language))->getMethod('extractTableValueArity');

        self::assertSame($expectedArity, $method->invoke($language, $sql));
    }

    /**
     * @param array<string, string> $expectedProperties
     */
    #[DataProvider('providerExtractIdentifierFreshnessProperties')]
    public function testExtractIdentifierFreshnessPropertiesNormalizesCanonicalIdentifiers(
        string $sql,
        array $expectedProperties,
    ): void {
        $language = SupportedLanguagePool::mysql('mysql-8.0.44');
        $method = (new \ReflectionClass($language))->getMethod('extractIdentifierFreshnessProperties');

        self::assertSame($expectedProperties, $method->invoke($language, $sql));
    }

    /**
     * @param list<string> $expectedAnchorRules
     */
    #[DataProvider('providerChangeReplicationSourceVersion')]
    public function testGeneratesChangeReplicationSourceWitnessForSupportedVersions(
        string $version,
        array $expectedAnchorRules,
        int $expectedSeed,
        string $expectedSql,
    ): void {
        $language = SupportedLanguagePool::mysql($version);
        $witness = $language->generateWitness(new FamilyRequest('mysql.constraint.change_replication_source'));

        self::assertSame(
            $expectedAnchorRules,
            $language->family('mysql.constraint.change_replication_source')->anchorRules,
        );
        self::assertSame($expectedSeed, $witness->seed);
        self::assertSame($expectedSql, $witness->sql);
    }

    #[DataProvider('providerLegacyVersionWithoutChangeReplicationSource')]
    public function testDoesNotExposeChangeReplicationSourceForLegacyVersions(string $version): void
    {
        $language = SupportedLanguagePool::mysql($version);
        $familyIds = array_map(
            static fn (FamilyDefinition $family): string => $family->id,
            $language->familyCatalog(),
        );

        self::assertNotContains('mysql.constraint.change_replication_source', $familyIds);
    }

    #[DataProvider('providerLegacyVersionWithoutChangeReplicationSource')]
    public function testRejectsChangeReplicationSourceWitnessForLegacyVersions(string $version): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown family: mysql.constraint.change_replication_source');
        $language = SupportedLanguagePool::mysql($version);
        $language->generateWitness(new FamilyRequest('mysql.constraint.change_replication_source'));
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>, 2: int, 3: string}>
     */
    public static function providerChangeReplicationSourceVersion(): iterable
    {
        yield 'mysql 8.0 source alias' => [
            'mysql-8.0.44',
            ['change'],
            2,
            "CHANGE REPLICATION SOURCE TO SOURCE_PASSWORD = 'BO18ZkuUmDkM'",
        ];
        yield 'mysql 8.4 dedicated statement' => [
            'mysql-8.4.7',
            ['change_replication_stmt'],
            1,
            "CHANGE REPLICATION SOURCE TO SOURCE_SSL_KEY = '4fJTlWtA62'",
        ];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function providerLegacyVersionWithoutChangeReplicationSource(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function providerDeterministicWitnessSql(): iterable
    {
        yield 'statement any' => ['mysql.statement.any', 'BEGIN'];
        yield 'statement select' => ['mysql.statement.select', 'WITH _i0 AS(SELECT _i1), _i2 AS(SELECT _i3) SELECT _i4 FOR UPDATE'];
        yield 'statement insert' => ['mysql.statement.insert', 'INSERT HIGH_PRIORITY `_i0`._i1 SET _i2 = _i3'];
        yield 'statement update' => ['mysql.statement.update', 'WITH _i0 AS(SELECT _i1), _i2 AS(SELECT _i3) UPDATE _i4 SET _i5 = _i6'];
        yield 'statement delete' => ['mysql.statement.delete', 'WITH _i0 AS(SELECT _i1), _i2 AS(SELECT _i3) DELETE _i4 FROM _i5'];
        yield 'set system variable' => ['mysql.constraint.set_system_variable', 'SET SESSION autocommit = 0'];
        yield 'create srs' => ['mysql.constraint.create_srs', 'CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 1 DESCRIPTION \'4fJTlWtA62\' ORGANIZATION \'ylRyZ1I\' IDENTIFIED BY 878115724 DEFINITION \'GEOGCS["WGS 84",DATUM["World Geodetic System 1984",SPHEROID["WGS 84",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278]]\' NAME \'9SrOoVZgqH1pllyYYGTpsyu65m6Hj\''];
        yield 'signal sqlstate' => ['mysql.constraint.signal_sqlstate', 'SIGNAL SQLSTATE \'45000\''];
        yield 'show warnings limit' => ['mysql.constraint.show_warnings.limit', 'SHOW WARNINGS LIMIT 0 OFFSET 0'];
        yield 'alter database encryption' => ['mysql.constraint.alter_database.encryption', 'ALTER DATABASE _i0 ENCRYPTION \'Y\''];
        yield 'identifier context' => ['mysql.lex.identifier.context', 'SELECT _i0'];
    }

    /**
     * @return iterable<string, array{0: int|string, 1: array<string, int>, 2: string}>
     */
    public static function providerDeterministicTableValueConstructorWitness(): iterable
    {
        yield 'arity 1' => [
            1,
            ['row_arity' => 1],
            "VALUES ROW('4fJTlWtA62'), ROW('ylRyZ1I')",
        ];
        yield 'arity 3 as string' => [
            '3',
            ['row_arity' => 3],
            "VALUES ROW('4fJTlWtA62', 'ylRyZ1I', '29SrOoVZgqH1'), ROW('llyYYGTpsyu65m6HjhGS5dFzvGEEqoC', 'w4VS05VWAI5BiKt8IO0yYOtQs', 'BMMqPYHROykG8qLn_HbApbxWezMlVlh')",
        ];
        yield 'arity 8' => [
            8,
            ['row_arity' => 8],
            "VALUES ROW('4fJTlWtA62', 'ylRyZ1I', '29SrOoVZgqH1', 'llyYYGTpsyu65m6HjhGS5dFzvGEEqoC', 'w4VS05VWAI5BiKt8IO0yYOtQs', 'BMMqPYHROykG8qLn_HbApbxWezMlVlh', 't', 'ZrmdtoEBHj8O7PjST45zTJZy6TzT0t'), ROW('TAW1zQM6pkrY7NOEKrWz7NvL', 'TqYB_YgfPgWuAKbuN13HRZy', 'boMljGPT6TDmjWNQaVylMw', 'yOYLhcmYBdJ9Wl6YYz9RG5lk1TDK', '38xa6IkTSykOqn2bDJSzi', '9SOeqUz9Bk8JzZKF0yZBSWGRQwHoZnIy', 'gWCL', '770xykT3ukLzlGImUyt2')",
        ];
    }

    /**
     * @return iterable<string, array{0: array<string, scalar>, 1: string}>
     */
    public static function providerInvalidTableValueConstructorRequest(): iterable
    {
        yield 'missing arity' => [
            [],
            'Missing required parameter arity for family mysql.constraint.table_value_constructor.',
        ];
        yield 'arity below range' => [
            ['arity' => 0],
            'arity parameter must be between 1 and 8.',
        ];
        yield 'arity above range' => [
            ['arity' => 9],
            'arity parameter must be between 1 and 8.',
        ];
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function providerExtractTableValueArity(): iterable
    {
        yield 'single value row' => ['VALUES ROW(1)', 1];
        yield 'multiple value row' => ['VALUES ROW(1, 2, 3)', 3];
        yield 'non values statement' => ['SELECT 1', 0];
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, string>}>
     */
    public static function providerExtractIdentifierFreshnessProperties(): iterable
    {
        yield 'canonical identifiers' => [
            'SELECT _i0, _i1',
            [
                'first_identifier' => '_i0',
                'second_identifier' => '_i1',
            ],
        ];
        yield 'identifiers are trimmed' => [
            'SELECT   _i0  ,   _i1   ',
            [
                'first_identifier' => '_i0',
                'second_identifier' => '_i1',
            ],
        ];
        yield 'trailing content is rejected' => [
            "SELECT _i0, _i1\nFROM _i2",
            [
                'first_identifier' => '',
                'second_identifier' => '',
            ],
        ];
        yield 'unrelated statement is rejected' => [
            'ALTER TABLE _i0',
            [
                'first_identifier' => '',
                'second_identifier' => '',
            ],
        ];
    }
}
