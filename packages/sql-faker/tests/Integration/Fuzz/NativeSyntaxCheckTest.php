<?php

declare(strict_types=1);

namespace Tests\Integration\SqlFaker\Fuzz;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Fuzz\Target\InfrastructureFailure;
use SqlFaker\Fuzz\Target\MySqlSyntaxCheck;
use SqlFaker\Fuzz\Target\OracleEnvironment;
use SqlFaker\Fuzz\Target\PgRawCheck;
use SqlFaker\Fuzz\Target\PgSyntaxCheck;
use SqlFaker\Fuzz\Target\SqliteProgramCheck;
use SqlFaker\Fuzz\Target\SqliteSyntaxCheck;
use SqlFaker\Fuzz\Target\SyntaxFailure;
use Tests\Fixtures\SqlFaker\OracleFixture;

#[CoversNothing]
final class NativeSyntaxCheckTest extends TestCase
{
    public function testMySqlSeparatesActualPrepareAcceptanceSemanticRejectionAndUnsupportedCommands(): void
    {
        $check = new MySqlSyntaxCheck(OracleFixture::mysql(), 'mysql-8.4.7', true);
        self::assertSame('accepted', $check->verify('SELECT 1', 'control')->status);
        self::assertSame('semantic-inconclusive', $check->verify('SELECT * FROM sqlfaker_missing', 'control')->status);
        self::assertSame('unsupported', $check->verify("PREPARE nested_statement FROM 'SELECT 1'", 'control')->status);
        self::assertSame('unsupported', $check->verify('', 'control')->status);
    }

    public function testMySqlRejectsTheIncompatibleIgnoreSpaceScannerMode(): void
    {
        $pdo = OracleFixture::mysql();
        $pdo->exec("SET SESSION sql_mode = 'IGNORE_SPACE'");
        $this->expectException(InfrastructureFailure::class);
        OracleEnvironment::mysql($pdo, 'mysql-8.4.7');
    }

    public function testMySqlDetectsInvalidSyntax(): void
    {
        $check = new MySqlSyntaxCheck(OracleFixture::mysql(), 'mysql-8.4.7');
        $this->expectException(SyntaxFailure::class);
        $check->verify('SELECT FROM', 'control');
    }

    public function testPostgreSqlSeparatesActualParseAcceptanceSemanticRejectionAndUnsupportedFeatures(): void
    {
        $check = new PgSyntaxCheck(OracleFixture::pg());
        self::assertSame('accepted', $check->verify('SELECT 1', 'control')->status);
        self::assertSame('semantic-inconclusive', $check->verify('SELECT * FROM sqlfaker_missing', 'control')->status);
        self::assertSame('unsupported', $check->verify('CREATE ASSERTION a CHECK (TRUE)', 'control')->status);
    }

    #[DataProvider('providerGrammarRejections')]
    public function testPostgreSqlDetectsActualGrammarActionRejections(string $sql): void
    {
        $check = new PgSyntaxCheck(OracleFixture::pg());
        $this->expectException(SyntaxFailure::class);
        $check->verify($sql, 'control');
    }

    /**
     * @return list<array{string}>
     */
    public static function providerGrammarRejections(): array
    {
        return [["SELECT * FROM JSON_TABLE('{}', 1 COLUMNS (x int PATH '$')) AS jt"], ['CREATE SCHEMA IF NOT EXISTS s CREATE TABLE t(a int)'], ['CREATE TABLE t(a int, CHECK(a>0) DEFERRABLE)'], ['CREATE TABLE t(a int REFERENCES u(a) ON UPDATE SET NULL(a))'], ['CREATE ROLE SESSION_USER'], ['CREATE TABLE t(a FLOAT(54))']];
    }

    #[DataProvider('providerRawModes')]
    public function testPostgreSqlRawParserAcceptsEveryDeclaredMode(int $mode, string $sql): void
    {
        OracleFixture::requiredSetting('SQLFAKER_PG_MODULE');
        $check = new PgRawCheck(OracleFixture::pg(), $mode, OracleFixture::requiredSetting('SQLFAKER_PG_MODULE'));
        self::assertSame('accepted', $check->verify($sql, 'control')->status);
    }

    /**
     * @return list<array{int, string}>
     */
    public static function providerRawModes(): array
    {
        return [[0, 'SELECT 1; SELECT 2;'], [1, 'integer[]'], [2, '1 + 2'], [3, 'x := 1'], [4, 'x.y := 1'], [5, 'x.y.z := 1']];
    }

    public function testPostgreSqlRawParserChecksTheSecondStatement(): void
    {
        $check = new PgRawCheck(OracleFixture::pg(), 0, OracleFixture::requiredSetting('SQLFAKER_PG_MODULE'));
        $this->expectException(SyntaxFailure::class);
        $check->verify('SELECT 1; SELECT FROM;', 'control');
    }

    public function testSqliteSeparatesActualAcceptanceAndSemanticRejection(): void
    {
        $check = new SqliteSyntaxCheck();
        self::assertSame('accepted', $check->verify('SELECT 1', 'control')->status);
        self::assertSame('semantic-inconclusive', $check->verify('SELECT * FROM sqlfaker_missing', 'control')->status);
    }

    public function testSqliteProgramMarksAnUnverifiedTailInconclusive(): void
    {
        $check = new SqliteProgramCheck(OracleFixture::requiredSetting('SQLFAKER_SQLITE_LIBRARY'));
        self::assertSame('accepted', $check->verify('SELECT 1; SELECT 2;', 'control')->status);
        $result = $check->verify('SELECT * FROM sqlfaker_missing; SELECT FROM;', 'control');
        self::assertSame('semantic-inconclusive', $result->status);
        self::assertSame('sqlite3_prepare_v3:tail-unverified', $result->source);
    }

    public function testSqliteProgramDetectsInvalidSyntaxInTheSecondStatement(): void
    {
        $check = new SqliteProgramCheck(OracleFixture::requiredSetting('SQLFAKER_SQLITE_LIBRARY'));
        $this->expectException(SyntaxFailure::class);
        $check->verify('SELECT 1; SELECT FROM;', 'control');
    }
}
