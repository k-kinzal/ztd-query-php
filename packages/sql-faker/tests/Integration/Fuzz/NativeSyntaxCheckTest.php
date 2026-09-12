<?php

declare (strict_types=1);

namespace Tests\Integration\SqlFaker\Fuzz;

use Faker\Factory;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SqlFaker\Fuzz\Target\InfrastructureFailure;
use SqlFaker\Fuzz\Target\MySqlSyntaxCheck;
use SqlFaker\Fuzz\Target\OracleEnvironment;
use SqlFaker\Fuzz\Target\PgRawCheck;
use SqlFaker\Fuzz\Target\PgSyntaxCheck;
use SqlFaker\Fuzz\Target\SqliteProgramCheck;
use SqlFaker\Fuzz\Target\SqliteSyntaxCheck;
use SqlFaker\Fuzz\Target\SyntaxFailure;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\PostgreSqlProvider;

#[CoversNothing]
#[Group('native')]
final class NativeSyntaxCheckTest extends TestCase
{
    public function testMySqlSeparatesActualPrepareAcceptanceSemanticRejectionAndUnsupportedCommands(): void
    {
        $dsn = getenv('SQLFAKER_MYSQL_DSN');
        self::assertIsString($dsn);
        self::assertNotSame('', $dsn);
        $pdo = new PDO(
            $dsn,
            OracleEnvironment::setting('SQLFAKER_MYSQL_USER', 'root'),
            OracleEnvironment::setting('SQLFAKER_MYSQL_PASSWORD', 'root'),
        );
        OracleEnvironment::mysql($pdo, 'mysql-8.4.7');
        $check = new MySqlSyntaxCheck($pdo, 'mysql-8.4.7', true);
        self::assertSame('accepted', $check->verify('SELECT 1', 'control')->status);
        self::assertSame('semantic-inconclusive', $check->verify('SELECT * FROM sqlfaker_missing', 'control')->status);
        self::assertSame('unsupported', $check->verify("PREPARE nested_statement FROM 'SELECT 1'", 'control')->status);
        self::assertSame('unsupported', $check->verify('', 'control')->status);
    }

    public function testMySqlRejectsTheIncompatibleIgnoreSpaceScannerMode(): void
    {
        $dsn = getenv('SQLFAKER_MYSQL_DSN');
        self::assertIsString($dsn);
        self::assertNotSame('', $dsn);
        $pdo = new PDO(
            $dsn,
            OracleEnvironment::setting('SQLFAKER_MYSQL_USER', 'root'),
            OracleEnvironment::setting('SQLFAKER_MYSQL_PASSWORD', 'root'),
        );
        OracleEnvironment::mysql($pdo, 'mysql-8.4.7');
        $pdo->exec("SET SESSION sql_mode = 'IGNORE_SPACE'");
        $this->expectException(InfrastructureFailure::class);
        OracleEnvironment::mysql($pdo, 'mysql-8.4.7');
    }

    public function testMySqlDetectsInvalidSyntax(): void
    {
        $dsn = getenv('SQLFAKER_MYSQL_DSN');
        self::assertIsString($dsn);
        self::assertNotSame('', $dsn);
        $pdo = new PDO(
            $dsn,
            OracleEnvironment::setting('SQLFAKER_MYSQL_USER', 'root'),
            OracleEnvironment::setting('SQLFAKER_MYSQL_PASSWORD', 'root'),
        );
        OracleEnvironment::mysql($pdo, 'mysql-8.4.7');
        $check = new MySqlSyntaxCheck($pdo, 'mysql-8.4.7');
        $this->expectException(SyntaxFailure::class);
        $check->verify('SELECT FROM', 'control');
    }

    public function testPostgreSqlSeparatesActualParseAcceptanceSemanticRejectionAndUnsupportedFeatures(): void
    {
        $connectionString = getenv('SQLFAKER_PG_CONNECTION');
        self::assertIsString($connectionString);
        self::assertNotSame('', $connectionString);
        $connection = pg_connect($connectionString);
        self::assertNotFalse($connection);
        OracleEnvironment::pg($connection);
        $check = new PgSyntaxCheck($connection);
        self::assertSame('accepted', $check->verify('SELECT 1', 'control')->status);
        self::assertSame('semantic-inconclusive', $check->verify('SELECT * FROM sqlfaker_missing', 'control')->status);
        self::assertSame('unsupported', $check->verify('CREATE ASSERTION a CHECK (TRUE)', 'control')->status);
    }
    #[DataProvider('providerGrammarRejections')]

    public function testPostgreSqlDetectsActualGrammarActionRejections(string $sql): void
    {
        $connectionString = getenv('SQLFAKER_PG_CONNECTION');
        self::assertIsString($connectionString);
        self::assertNotSame('', $connectionString);
        $connection = pg_connect($connectionString);
        self::assertNotFalse($connection);
        OracleEnvironment::pg($connection);
        $check = new PgSyntaxCheck($connection);
        $this->expectException(SyntaxFailure::class);
        $check->verify($sql, 'control');
    }

    /**
     * @return list<array{string}>
     */

    public static function providerGrammarRejections(): array
    {
        return [
            ["SELECT * FROM JSON_TABLE('{}', 1 COLUMNS (x int PATH '\$')) AS jt"],
            ['CREATE SCHEMA IF NOT EXISTS s CREATE TABLE t(a int)'],
            ['CREATE TABLE t(a int, CHECK(a>0) DEFERRABLE)'],
            ['CREATE TABLE t(a int REFERENCES u(a) ON UPDATE SET NULL(a))'],
            ['CREATE ROLE SESSION_USER'],
            ['CREATE TABLE t(a FLOAT(54))'],
        ];
    }
    #[DataProvider('providerRawModes')]

    public function testPostgreSqlRawParserAcceptsEveryDeclaredMode(int $mode, string $sql): void
    {
        $connectionString = getenv('SQLFAKER_PG_CONNECTION');
        self::assertIsString($connectionString);
        self::assertNotSame('', $connectionString);
        $connection = pg_connect($connectionString);
        self::assertNotFalse($connection);
        OracleEnvironment::pg($connection);
        $module = getenv('SQLFAKER_PG_MODULE');
        self::assertIsString($module);
        self::assertNotSame('', $module);
        $check = new PgRawCheck($connection, $mode, $module);
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
        $connectionString = getenv('SQLFAKER_PG_CONNECTION');
        self::assertIsString($connectionString);
        self::assertNotSame('', $connectionString);
        $connection = pg_connect($connectionString);
        self::assertNotFalse($connection);
        OracleEnvironment::pg($connection);
        $module = getenv('SQLFAKER_PG_MODULE');
        self::assertIsString($module);
        self::assertNotSame('', $module);
        $check = new PgRawCheck($connection, 0, $module);
        $this->expectException(SyntaxFailure::class);
        $check->verify('SELECT 1; SELECT FROM;', 'control');
    }

    public function testPostgreSqlRawParserAcceptsTheSavedPlpgsqlFetchWithTiesInput(): void
    {
        $connectionString = getenv('SQLFAKER_PG_CONNECTION');
        self::assertIsString($connectionString);
        self::assertNotSame('', $connectionString);
        $connection = pg_connect($connectionString);
        self::assertNotFalse($connection);
        OracleEnvironment::pg($connection);
        $module = getenv('SQLFAKER_PG_MODULE');
        self::assertIsString($module);
        self::assertNotSame('', $module);
        $hex = '5b04005353535f5f5f5f5fa0a0a0a0a027f1f132f12727f1f1535353535353535353372a9e9e9e5353535353535353535353535353535353535353535353535353535353535353535353535353535353535353535353535353535353535327a0a053535353535353535353535353535353535353535353535353595353535353535353535353535353535353539a9e27';
        $input = hex2bin($hex);
        self::assertIsString($input);
        $provider = new PostgreSqlProvider(Factory::create(), 'pg-17.2');
        $constraints = GenerationPlan::constrained('parse_toplevel', ['parse_toplevel' => [ProductionPattern::at(3)]])->withExpansionBudget(100);
        $plan = (new \SqlFaker\Generation\Choice\BytePlanCompiler())->compile(substr($input, 1), $provider->planner(), $constraints);
        $sql = $provider->generate($plan);
        self::assertSame($sql, $provider->generate($plan));
        $check = new PgRawCheck($connection, 3, $module);
        self::assertSame('accepted', $check->verify($sql, $hex)->status);
    }
    #[DataProvider('providerPlpgsqlFetchRejections')]

    public function testPostgreSqlRawParserRejectsPlpgsqlFetchWithTiesWithoutOrdering(int $mode, string $sql): void
    {
        $connectionString = getenv('SQLFAKER_PG_CONNECTION');
        self::assertIsString($connectionString);
        self::assertNotSame('', $connectionString);
        $connection = pg_connect($connectionString);
        self::assertNotFalse($connection);
        OracleEnvironment::pg($connection);
        $module = getenv('SQLFAKER_PG_MODULE');
        self::assertIsString($module);
        self::assertNotSame('', $module);
        $check = new PgRawCheck($connection, $mode, $module);
        $this->expectException(SyntaxFailure::class);
        $check->verify($sql, 'control');
    }

    /**
     * @return list<array{int, string}>
     */

    public static function providerPlpgsqlFetchRejections(): array
    {
        return [
            [2, 'ALL FETCH NEXT ROWS WITH TIES'],
            [3, '$8 := ALL FETCH NEXT ROWS WITH TIES'],
            [4, 'x.y := ALL FETCH NEXT ROWS WITH TIES'],
            [5, 'x.y.z := ALL FETCH NEXT ROWS WITH TIES'],
        ];
    }

    public function testSqliteSeparatesActualAcceptanceAndSemanticRejection(): void
    {
        $check = new SqliteSyntaxCheck();
        self::assertSame('accepted', $check->verify('SELECT 1', 'control')->status);
        self::assertSame('semantic-inconclusive', $check->verify('SELECT * FROM sqlfaker_missing', 'control')->status);
    }

    public function testSqliteProgramMarksAnUnverifiedTailInconclusive(): void
    {
        $library = getenv('SQLFAKER_SQLITE_LIBRARY');
        self::assertIsString($library);
        self::assertNotSame('', $library);
        $check = new SqliteProgramCheck($library);
        self::assertSame('accepted', $check->verify('SELECT 1; SELECT 2;', 'control')->status);
        $result = $check->verify('SELECT * FROM sqlfaker_missing; SELECT FROM;', 'control');
        self::assertSame('semantic-inconclusive', $result->status);
        self::assertSame('sqlite3_prepare_v3:tail-unverified', $result->source);
    }

    public function testSqliteProgramDetectsInvalidSyntaxInTheSecondStatement(): void
    {
        $library = getenv('SQLFAKER_SQLITE_LIBRARY');
        self::assertIsString($library);
        self::assertNotSame('', $library);
        $check = new SqliteProgramCheck($library);
        $this->expectException(SyntaxFailure::class);
        $check->verify('SELECT 1; SELECT FROM;', 'control');
    }
}
