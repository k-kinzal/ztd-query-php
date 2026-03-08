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
        yield 'create srs' => ['mysql.constraint.create_srs', 'CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 1 DESCRIPTION \'4fJTlWtA62\' ORGANIZATION \'ylRyZ1I\' IDENTIFIED BY 878115724 DEFINITION \'GEOGCS["WGS 84",DATUM["World Geodetic System 1984",SPHEROID["WGS 84",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278]]\' NAME \'9SrOoVZgqH1pllyYYGTpsyu65m6Hj\''];
        yield 'alter database encryption' => ['mysql.constraint.alter_database.encryption', 'ALTER DATABASE _i0 ENCRYPTION \'Y\''];
    }
}
