<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

require_once dirname(__DIR__, 2) . '/Support/SqlFaker/Contract/SupportedLanguageContractAssertions.php';

use PHPUnit\Framework\Attributes\CoversClass;
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
use SqlFaker\MySql\SqlGenerator as MySqlSqlGenerator;
use SqlFaker\MySql\SupportedLanguage as MySqlSupportedLanguage;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSql\SqlGenerator as PostgreSqlSqlGenerator;
use SqlFaker\PostgreSql\SupportedLanguage as PostgreSqlSupportedLanguage;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\Sqlite\SqlGenerator as SqliteSqlGenerator;
use SqlFaker\Sqlite\SupportedLanguage as SqliteSupportedLanguage;
use SqlFaker\SqliteProvider;
use Tests\Support\SqlFaker\Contract\SupportedLanguageContractAssertions;

#[CoversClass(MySqlSupportedLanguage::class)]
#[CoversClass(PostgreSqlSupportedLanguage::class)]
#[CoversClass(SqliteSupportedLanguage::class)]
#[UsesClass(FamilyDefinition::class)]
#[UsesClass(FamilyRequest::class)]
#[UsesClass(GrammarAlternativeSnapshot::class)]
#[UsesClass(GrammarRuleSnapshot::class)]
#[UsesClass(GrammarSnapshot::class)]
#[UsesClass(GrammarSnapshotBuilder::class)]
#[UsesClass(GrammarSymbolSnapshot::class)]
#[UsesClass(SqlWitness::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(MySqlSqlGenerator::class)]
#[UsesClass(MySqlProvider::class)]
#[UsesClass(PostgreSqlSqlGenerator::class)]
#[UsesClass(PostgreSqlProvider::class)]
#[UsesClass(SqliteSqlGenerator::class)]
#[UsesClass(SqliteProvider::class)]
final class SupportedLanguageTest extends TestCase
{
    public function testMySqlSupportedLanguageExposesPublicContract(): void
    {
        $language = new MySqlSupportedLanguage('mysql-8.0.44');

        self::assertSame('mysql', $language->dialect());
        self::assertNotSame('', $language->grammarSnapshot()->startRule);
        self::assertSame(['simple_statement_or_begin'], $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['simple_statement_or_begin'],
            $language->family('mysql.statement.any')->anchorRules,
        );
        self::assertNotSame(
            '',
            $language->generateWitness(new FamilyRequest('mysql.statement.select'))->sql,
        );
    }

    public function testMySqlSupportedLanguageSnapshotFingerprintIsStable(): void
    {
        self::assertSame(
            'be2ebf7b66a7337598127a81b392760ede6d876850feea6a74a8eab9c2832097',
            SupportedLanguageContractAssertions::contractFingerprint(new MySqlSupportedLanguage('mysql-8.0.44')),
        );
    }

    public function testMySqlSupportedLanguageWitnessFingerprintIsStable(): void
    {
        self::assertSame(
            'c5c182dd3ff6204c46e6ce5fb2cc1d74dd280c2f85c7c96578687c362edc3618',
            SupportedLanguageContractAssertions::witnessFingerprint(new MySqlSupportedLanguage('mysql-8.0.44')),
        );
    }

    public function testMySqlSupportedLanguageGeneratesWitnessesForEveryFamily(): void
    {
        SupportedLanguageContractAssertions::assertGeneratesWitnessesForEveryFamily(
            new MySqlSupportedLanguage('mysql-8.0.44'),
        );
    }

    public function testPostgreSqlSupportedLanguageExposesPublicContract(): void
    {
        $language = new PostgreSqlSupportedLanguage();

        self::assertSame('postgresql', $language->dialect());
        self::assertNotSame('', $language->grammarSnapshot()->startRule);
        self::assertContains('stmtmulti', $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['SelectStmt', 'distinct_clause', 'safe_distinct_on_expr_list'],
            $language->family('postgresql.constraint.distinct_on')->anchorRules,
        );
        self::assertNotSame(
            '',
            $language->generateWitness(new FamilyRequest('postgresql.statement.select'))->sql,
        );
    }

    public function testPostgreSqlSupportedLanguageSnapshotFingerprintIsStable(): void
    {
        self::assertSame(
            'b226440418850a9a412d8a7c0e0b74004305d14bb5e981e05ac8f62e3154b264',
            SupportedLanguageContractAssertions::contractFingerprint(new PostgreSqlSupportedLanguage()),
        );
    }

    public function testPostgreSqlSupportedLanguageWitnessFingerprintIsStable(): void
    {
        self::assertSame(
            'd6062ff9edab79fc70ee62cd06e5047a53e6c165a5e0a5882bdb4cf20fe54d54',
            SupportedLanguageContractAssertions::witnessFingerprint(new PostgreSqlSupportedLanguage()),
        );
    }

    public function testPostgreSqlSupportedLanguageGeneratesWitnessesForEveryFamily(): void
    {
        SupportedLanguageContractAssertions::assertGeneratesWitnessesForEveryFamily(
            new PostgreSqlSupportedLanguage(),
        );
    }

    public function testSqliteSupportedLanguageExposesPublicContract(): void
    {
        $language = new SqliteSupportedLanguage();

        self::assertSame('sqlite', $language->dialect());
        self::assertNotSame('', $language->grammarSnapshot()->startRule);
        self::assertContains('cmd', $language->grammarSnapshot()->entryRules);
        self::assertSame(
            ['attach_stmt', 'safe_attach_filename_expr', 'safe_attach_schema_expr'],
            $language->family('sqlite.constraint.attach.expression')->anchorRules,
        );
        self::assertNotSame(
            '',
            $language->generateWitness(new FamilyRequest('sqlite.statement.select'))->sql,
        );
    }

    public function testSqliteSupportedLanguageSnapshotFingerprintIsStable(): void
    {
        self::assertSame(
            '38d44310096587006d846b11cb076c2205810fd5a036ea61b416719aba064b36',
            SupportedLanguageContractAssertions::contractFingerprint(new SqliteSupportedLanguage()),
        );
    }

    public function testSqliteSupportedLanguageWitnessFingerprintIsStable(): void
    {
        self::assertSame(
            'b7e7f7e2174dd83b729fba1c1df8bc5704d3f2bfdc56433ddb6d6013236f1892',
            SupportedLanguageContractAssertions::witnessFingerprint(new SqliteSupportedLanguage()),
        );
    }

    public function testSqliteSupportedLanguageGeneratesWitnessesForEveryFamily(): void
    {
        SupportedLanguageContractAssertions::assertGeneratesWitnessesForEveryFamily(
            new SqliteSupportedLanguage(),
        );
    }
}
