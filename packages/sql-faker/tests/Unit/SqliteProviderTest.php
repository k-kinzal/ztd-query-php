<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Sqlite\SqlGenerator;
use SqlFaker\Sqlite\StatementType;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\SqliteProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SqliteProvider::class)]
#[CoversClass(TokenJoiner::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(SqlGenerator::class)]
#[CoversClass(SqliteGrammar::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(Production::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(TerminationAnalyzer::class)]
#[CoversClass(StatementType::class)]
final class SqliteProviderTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        gc_collect_cycles();
    }

    public function testConstructorRegistersProviderWithFaker(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);

        /** @var list<object> $providers */
        $providers = $faker->getProviders();
        self::assertContains($provider, $providers);

        $identifier = $provider->identifier(3);
        self::assertNotSame('', $identifier);
    }

    public function testSql(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->sql(maxDepth: 6);

        self::assertNotSame('', $result);
    }

    public function testSqlWithStatementType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->sql(StatementType::Select, maxDepth: 6);

        self::assertTrue(
            str_contains($result, 'SELECT') || str_contains($result, 'VALUES'),
            "SelectStmt should produce SELECT or VALUES: {$result}"
        );
    }

    public function testSqlWithNullStatementTypeUsesRandom(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->sql(null, maxDepth: 6);

        self::assertNotSame('', $result);
    }

    public function testSqlWithMaxDepth(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->sql(maxDepth: 8);

        self::assertNotSame('', $result);
    }

    public function testSelectStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->selectStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertTrue(
            str_contains($result, 'SELECT') || str_contains($result, 'VALUES'),
            "select should contain SELECT or VALUES: {$result}"
        );
    }

    public function testInsertStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->insertStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertTrue(
            str_contains($result, 'INSERT') || str_contains($result, 'REPLACE'),
            "insert should contain INSERT or REPLACE: {$result}"
        );
    }

    public function testUpdateStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->updateStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('UPDATE', $result);
    }

    public function testDeleteStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->deleteStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('DELETE', $result);
    }

    public function testCreateTableStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->createTableStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('CREATE', $result);
        self::assertStringContainsString('TABLE', $result);
    }

    public function testAlterTableStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->alterTableStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('ALTER', $result);
        self::assertStringContainsString('TABLE', $result);
    }

    public function testDropTableStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->dropTableStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('DROP', $result);
        self::assertStringContainsString('TABLE', $result);
    }

    public function testExpr(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->expr(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testTerm(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->term(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testWhereClause(): void
    {
        $faker = Factory::create();
        $faker->seed(1);
        $provider = new SqliteProvider($faker);

        $result = $provider->whereClause(maxDepth: 6);

        self::assertMatchesRegularExpression('/^$|WHERE/', $result);
    }

    public function testOrderByClause(): void
    {
        $faker = Factory::create();
        $faker->seed(1);
        $provider = new SqliteProvider($faker);

        $result = $provider->orderByClause(maxDepth: 6);

        self::assertMatchesRegularExpression('/^$|ORDER/', $result);
    }

    public function testLimitClause(): void
    {
        $faker = Factory::create();
        $faker->seed(1);
        $provider = new SqliteProvider($faker);

        $result = $provider->limitClause(maxDepth: 6);

        self::assertMatchesRegularExpression('/^$|LIMIT/', $result);
    }

    public function testGroupByClause(): void
    {
        $faker = Factory::create();
        $faker->seed(1);
        $provider = new SqliteProvider($faker);

        $result = $provider->groupByClause(maxDepth: 6);

        self::assertMatchesRegularExpression('/^$|GROUP/', $result);
    }

    public function testHavingClause(): void
    {
        $faker = Factory::create();
        $faker->seed(1);
        $provider = new SqliteProvider($faker);

        $result = $provider->havingClause(maxDepth: 6);

        self::assertMatchesRegularExpression('/^$|HAVING/', $result);
    }

    public function testFullname(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->fullname(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testWithClause(): void
    {
        $faker = Factory::create();
        $faker->seed(0);
        $provider = new SqliteProvider($faker);

        $result = $provider->withClause(maxDepth: 6);

        self::assertMatchesRegularExpression('/^$|WITH/', $result);
    }

    public function testIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->identifier(3);

        self::assertNotSame('', $result);
    }

    public function testQuotedIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->quotedIdentifier();

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]*"$/', $result);
    }

    public function testStringLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->stringLiteral();

        self::assertMatchesRegularExpression("/^'[a-zA-Z0-9_]{1,255}'$/", $result);
    }

    public function testIntegerLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->integerLiteral();

        self::assertMatchesRegularExpression('/^[1-9]\d*$/', $result);
    }

    public function testDecimalLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->decimalLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testQuotedIdentifierDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new SqliteProvider($faker);
        $faker->seed(42);
        $a = $p->quotedIdentifier();
        $faker->seed(42);
        self::assertSame($a, $p->quotedIdentifier(1, 128));
    }

    public function testStringLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new SqliteProvider($faker);
        $faker->seed(42);
        $a = $p->stringLiteral();
        $faker->seed(42);
        self::assertSame($a, $p->stringLiteral(1, 255));
    }

    public function testIntegerLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new SqliteProvider($faker);
        $faker->seed(42);
        $a = $p->integerLiteral();
        $faker->seed(42);
        self::assertSame($a, $p->integerLiteral(1, PHP_INT_MAX));
    }

    public function testDecimalLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new SqliteProvider($faker);
        $faker->seed(42);
        $a = $p->decimalLiteral();
        $faker->seed(42);
        self::assertSame($a, $p->decimalLiteral(15, 2));
    }

    public function testQuotedIdentifierCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->quotedIdentifier(5, 10);

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]{4,9}"$/', $result);
    }

    public function testStringLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->stringLiteral(3, 8);
        $content = substr($result, 1, -1);

        self::assertGreaterThanOrEqual(3, strlen($content));
        self::assertLessThanOrEqual(8, strlen($content));
    }

    public function testIntegerLiteralCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->integerLiteral(100, 500);

        self::assertGreaterThanOrEqual(100, (int) $result);
        self::assertLessThanOrEqual(500, (int) $result);
    }

    public function testDecimalLiteralCustomPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->decimalLiteral(5, 2);

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testSeededGenerationIsReproducible(): void
    {
        $faker1 = Factory::create();
        $provider1 = new SqliteProvider($faker1);
        $faker1->seed(99999);
        $sql1 = $provider1->sql(maxDepth: 8);

        $faker2 = Factory::create();
        $provider2 = new SqliteProvider($faker2);
        $faker2->seed(99999);
        $sql2 = $provider2->sql(maxDepth: 8);

        self::assertSame($sql1, $sql2, 'Same seed should produce same output');
    }

    #[DataProvider('providerStatementTypeValue')]
    public function testSqlWithAllStatementTypes(StatementType $type): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->sql($type, maxDepth: 6);

        self::assertNotSame('', $result);
    }

    public function testSelectContainsSelectOrValues(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $sql = $provider->selectStatement(maxDepth: 6);

        self::assertTrue(
            str_contains($sql, 'SELECT') || str_contains($sql, 'VALUES'),
            "select should produce SELECT or VALUES: {$sql}"
        );
    }

    public function testUpdateContainsSetClause(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->updateStatement(maxDepth: 6);

        self::assertStringContainsString('UPDATE', $result);
        self::assertStringContainsString('SET', $result);
    }

    public function testDeleteContainsDeleteKeyword(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->deleteStatement(maxDepth: 6);

        self::assertStringContainsString('DELETE', $result);
    }

    public function testMultipleGenerationsReturnDifferentResults(): void
    {
        $faker1 = Factory::create();
        $faker1->seed(0);
        $provider1 = new SqliteProvider($faker1);
        $sql1 = $provider1->selectStatement(maxDepth: 6);

        $faker2 = Factory::create();
        $faker2->seed(1);
        $provider2 = new SqliteProvider($faker2);
        $sql2 = $provider2->selectStatement(maxDepth: 6);

        self::assertNotSame($sql1, $sql2, 'Different seeds should produce different SQL');
    }

    public function testGrammarDrivenOutputIsNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $sql = $provider->sql(maxDepth: 8);

        self::assertNotSame('', $sql, 'Generated SQL must not be empty');
    }

    public function testSimpleStatementReturnsNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        self::assertNotSame('', $provider->simpleStatement(maxDepth: 6));
    }

    public function testAlterTableOperations(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $sql = $provider->alterTableStatement(maxDepth: 6);

        self::assertTrue(
            str_contains($sql, 'RENAME')
            || str_contains($sql, 'ADD')
            || str_contains($sql, 'DROP'),
            "ALTER TABLE should use RENAME, ADD, or DROP: {$sql}"
        );
    }

    public function testDropTableContainsDropTable(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $sql = $provider->dropTableStatement(maxDepth: 6);

        self::assertStringContainsString('DROP TABLE', $sql, "DROP TABLE must be present: {$sql}");
    }

    public function testInsertContainsInto(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $sql = $provider->insertStatement(maxDepth: 6);

        self::assertStringContainsString('INTO', $sql, "INSERT must contain INTO: {$sql}");
    }

    public function testCreateTableContainsTable(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $sql = $provider->createTableStatement(maxDepth: 6);

        self::assertStringContainsString('TABLE', $sql, "CREATE TABLE must contain TABLE: {$sql}");
    }

    /**
     * @return iterable<string, array{StatementType}>
     */
    public static function providerStatementTypeValue(): iterable
    {
        yield 'Select' => [StatementType::Select];
        yield 'Insert' => [StatementType::Insert];
        yield 'Update' => [StatementType::Update];
        yield 'Delete' => [StatementType::Delete];
        yield 'CreateTable' => [StatementType::CreateTable];
        yield 'AlterTable' => [StatementType::AlterTable];
        yield 'DropTable' => [StatementType::DropTable];
    }
}
