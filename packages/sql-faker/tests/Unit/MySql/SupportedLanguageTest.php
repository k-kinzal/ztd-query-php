<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use LogicException;
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
    public function testExposesMySqlSupportedLanguageContract(): void
    {
        $language = new SupportedLanguage('mysql-8.0.44');
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
        $language = new SupportedLanguage('mysql-8.0.44');

        $witness = $language->generateWitness(new FamilyRequest($familyId));

        self::assertSame(1, $witness->seed);
        self::assertSame($expectedSql, $witness->sql);
    }

    public function testGenerateWitnessRejectsUnknownParametersBeforeSearching(): void
    {
        $language = new SupportedLanguage('mysql-8.0.44');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown parameters for family mysql.statement.select: unexpected');
        $language->generateWitness(new FamilyRequest('mysql.statement.select', ['unexpected' => 1]));
    }

    public function testGenerateWitnessRejectsUnknownFamiliesBeforeDispatch(): void
    {
        $language = new SupportedLanguage('mysql-8.0.44');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown family: mysql.unknown.family');
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
        $language = new SupportedLanguage('mysql-8.0.44');

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
        $language = new SupportedLanguage('mysql-8.0.44');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedMessage);
        $language->generateWitness(new FamilyRequest('mysql.constraint.table_value_constructor', $parameters));
    }

    public function testGenerateWitnessUsesDeterministicIdentifierFreshnessWitness(): void
    {
        $language = new SupportedLanguage('mysql-8.0.44');

        $witness = $language->generateWitness(new FamilyRequest('mysql.lex.identifier.freshness'));

        self::assertSame(1, $witness->seed);
        self::assertSame('SELECT _i0, _i1', $witness->sql);
        self::assertSame([
            'first_identifier' => '_i0',
            'second_identifier' => '_i1',
        ], $witness->properties);
    }

    /**
     * @param list<string> $expectedAnchorRules
     */
    #[DataProvider('providerChangeReplicationSourceVersion')]
    public function testGeneratesChangeReplicationSourceWitnessForSupportedVersions(
        string $version,
        array $expectedAnchorRules,
    ): void {
        $language = new SupportedLanguage($version);
        $witness = $language->generateWitness(new FamilyRequest('mysql.constraint.change_replication_source'));

        self::assertSame(
            $expectedAnchorRules,
            $language->family('mysql.constraint.change_replication_source')->anchorRules,
        );
        self::assertStringStartsWith('CHANGE REPLICATION SOURCE TO ', $witness->sql);
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function providerChangeReplicationSourceVersion(): iterable
    {
        yield 'mysql 8.0 source alias' => ['mysql-8.0.44', ['change']];
        yield 'mysql 8.4 dedicated statement' => ['mysql-8.4.7', ['change_replication_stmt']];
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
}
