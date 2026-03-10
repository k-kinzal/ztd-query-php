<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;
use SqlFaker\MySql\SqlGenerator;
use SqlFaker\MySql\StatementType;
use SqlFaker\MySqlProvider;

#[CoversClass(MySqlProvider::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(SqlGenerator::class)]
#[UsesClass(GenerationRequest::class)]
#[UsesClass(ContractGrammarProjector::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\TokenJoiner::class)]
#[UsesClass(\SqlFaker\Contract\Grammar::class)]
#[UsesClass(\SqlFaker\Contract\ProductionRule::class)]
#[UsesClass(\SqlFaker\Contract\Production::class)]
#[UsesClass(\SqlFaker\Contract\Symbol::class)]
#[Medium]
final class MySqlProviderTest extends TestCase
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
        $provider = new MySqlProvider($faker);

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
        $provider = new MySqlProvider($faker);

        $result = $provider->sql(maxDepth: 10);

        self::assertNotSame('', $result);
    }

    public function testSqlWithStatementType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->sql(StatementType::Select, maxDepth: 3);

        self::assertMatchesRegularExpression('/\bSELECT\b/i', $result);
    }

    public function testSqlWithNullStatementTypeUsesDefault(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->sql(null, maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testSqlWithMaxDepth(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->sql(maxDepth: 5);

        self::assertNotSame('', $result);
    }

    public function testSelectStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->selectStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bSELECT\b/i', $result);
    }

    public function testInsertStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->insertStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bINSERT\b/i', $result);
    }

    public function testUpdateStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->updateStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bUPDATE\b/i', $result);
    }

    public function testDeleteStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->deleteStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bDELETE\b/i', $result);
    }

    public function testCreateTableStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->createTableStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bCREATE\b/i', $result);
        self::assertMatchesRegularExpression('/\bTABLE\b/i', $result);
    }

    public function testAlterTableStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->alterTableStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bALTER\b/i', $result);
        self::assertMatchesRegularExpression('/\bTABLE\b/i', $result);
    }

    public function testDropTableStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->dropTableStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bDROP\b/i', $result);
        self::assertMatchesRegularExpression('/\bTABLE\b/i', $result);
    }

    public function testSimpleStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->simpleStatement(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testReplaceStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->replaceStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bREPLACE\b/i', $result);
    }

    public function testTruncateStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->truncateStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bTRUNCATE\b/i', $result);
    }

    public function testCreateIndexStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->createIndexStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bCREATE\b/i', $result);
        self::assertMatchesRegularExpression('/\bINDEX\b/i', $result);
    }

    public function testDropIndexStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->dropIndexStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bDROP\b/i', $result);
        self::assertMatchesRegularExpression('/\bINDEX\b/i', $result);
    }

    public function testBeginStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->beginStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bBEGIN\b/i', $result);
    }

    public function testCommitStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->commitStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bCOMMIT\b/i', $result);
    }

    public function testRollbackStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->rollbackStatement(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bROLLBACK\b/i', $result);
    }

    public function testIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->identifier(3);

        self::assertNotSame('', $result);
    }

    #[DataProvider('providerCanonicalIdentifierSeed')]
    public function testIdentifierAvoidsContextSensitiveKeywords(int $seed): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);

        $faker->seed($seed);

        self::assertDoesNotMatchRegularExpression('/^(ACTION|EVENT|VIEW|CURRENT_USER)$/i', $provider->identifier(3));
    }

    public function testExpr(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->expr(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testSimpleExpr(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->simpleExpr(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->literal(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testPredicate(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->predicate(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testWhereClause(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->whereClause(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bWHERE\b/i', $result);
    }

    public function testOrderClause(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->orderClause(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bORDER\s+BY\b/i', $result);
    }

    public function testLimitClause(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->limitClause(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bLIMIT\b/i', $result);
    }

    public function testTableReference(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->tableReference(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testJoinedTable(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->joinedTable(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bJOIN\b/i', $result);
    }

    public function testTableIdent(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->tableIdent(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testSubquery(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->subquery(maxDepth: 3);

        self::assertStringContainsString('(', $result);
        self::assertStringContainsString(')', $result);
    }

    public function testWithClause(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->withClause(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bWITH\b/i', $result);
    }

    public function testSeededGenerationIsReproducible(): void
    {
        $faker1 = Factory::create();
        $provider1 = new MySqlProvider($faker1);
        $faker1->seed(99999);
        $sql1 = $provider1->sql(maxDepth: 6);

        $faker2 = Factory::create();
        $provider2 = new MySqlProvider($faker2);
        $faker2->seed(99999);
        $sql2 = $provider2->sql(maxDepth: 6);

        self::assertSame($sql1, $sql2, 'Same seed should produce same output');
    }

    public function testCanBeUsedViaFakerMagicMethod(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        new MySqlProvider($faker);

        $sql = $faker->format('sql', [null, 10]);
        self::assertIsString($sql);
        self::assertNotSame('', $sql);
    }

    #[DataProvider('providerStatementTypeValue')]
    public function testSqlWithAllStatementTypes(StatementType $type): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->sql($type, maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testDefaultMaxDepthIsPhpIntMax(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->sql(maxDepth: 10);

        self::assertNotSame('', $result);
    }

    public function testSelectStatementMinimalDepthProducesOutput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->selectStatement(maxDepth: 1);

        self::assertMatchesRegularExpression('/\bSELECT\b/i', $result);
    }

    #[DataProvider('providerMultipleGenerationSeeds')]
    public function testMultipleGenerationsReturnDifferentResults(int $seed1, int $seed2): void
    {
        $faker1 = Factory::create();
        $faker1->seed($seed1);
        $provider1 = new MySqlProvider($faker1);

        $faker2 = Factory::create();
        $faker2->seed($seed2);
        $provider2 = new MySqlProvider($faker2);

        self::assertNotSame($provider1->selectStatement(maxDepth: 3), $provider2->selectStatement(maxDepth: 3));
    }

    public function testRuntimeContractExposesSnapshotSupportedGrammarAndDeterministicGeneration(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker, 'mysql-8.0.44');

        self::assertNotSame('', $provider->snapshot()->startSymbol);
        self::assertSame($provider->snapshot()->startSymbol, $provider->supportedGrammar()->startSymbol);
        self::assertNotNull($provider->supportedGrammar()->rule('rollback'));
        self::assertSame(
            $provider->generate(new GenerationRequest('ident', 11, 1)),
            $provider->generate(new GenerationRequest('ident', 11, 1)),
        );
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
        yield 'SimpleStatement' => [StatementType::SimpleStatement];
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function providerMultipleGenerationSeeds(): iterable
    {
        yield 'seeds 0 and 1' => [0, 1];
        yield 'seeds 5 and 10' => [5, 10];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerCanonicalIdentifierSeed(): iterable
    {
        foreach (range(0, 512) as $seed) {
            yield "seed {$seed}" => [$seed];
        }
    }
}

#[CoversClass(MySqlProvider::class)]
#[CoversClass(RandomStringGenerator::class)]
final class MySqlProviderHelperTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        gc_collect_cycles();
    }

    public function testQuotedIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->quotedIdentifier();

        self::assertMatchesRegularExpression('/^`[a-z_][a-z0-9_]*`$/', $result);
    }

    public function testStringLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->stringLiteral();

        self::assertMatchesRegularExpression("/^'[a-zA-Z0-9_]{1,32}'$/", $result);
    }

    public function testStringLiteralLengthRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->stringLiteral();
        $content = substr($result, 1, -1);

        self::assertGreaterThanOrEqual(1, strlen($content));
        self::assertLessThanOrEqual(32, strlen($content));
    }

    public function testNationalStringLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->nationalStringLiteral();

        self::assertMatchesRegularExpression("/^N'[a-zA-Z0-9_]{1,32}'$/", $result);
    }

    public function testDollarQuotedString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->dollarQuotedString();

        self::assertMatchesRegularExpression('/^\$\$[a-zA-Z0-9_]{1,32}\$\$$/', $result);
    }

    public function testIntegerLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->integerLiteral();

        self::assertMatchesRegularExpression('/^[1-9]\d*$/', $result);
    }

    public function testLongIntegerLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->longIntegerLiteral();

        self::assertMatchesRegularExpression('/^\d+$/', $result);
        self::assertGreaterThanOrEqual(0, (int) $result);
        self::assertLessThanOrEqual(2147483647, (int) $result);
    }

    public function testUnsignedBigIntLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->unsignedBigIntLiteral();

        self::assertMatchesRegularExpression('/^\d+$/', $result);
    }

    public function testDecimalLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->decimalLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->floatLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->hexLiteral();

        self::assertMatchesRegularExpression('/^0x[0-9a-f]{1,16}$/', $result);
    }

    public function testBinaryLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->binaryLiteral();

        self::assertMatchesRegularExpression('/^0b[01]{1,64}$/', $result);
    }

    public function testHostname(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->hostname();

        self::assertMatchesRegularExpression('/^[a-z0-9]+(\.[a-z0-9]+)*$/', $result);
    }

    public function testQuotedIdentifierDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->quotedIdentifier();
        $faker->seed(42);

        self::assertSame($result, $provider->quotedIdentifier(1, 64));
    }

    public function testStringLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->stringLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->stringLiteral(1, 32));
    }

    public function testNationalStringLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->nationalStringLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->nationalStringLiteral(1, 32));
    }

    public function testDollarQuotedStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->dollarQuotedString();
        $faker->seed(42);

        self::assertSame($result, $provider->dollarQuotedString(1, 32));
    }

    public function testIntegerLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->integerLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->integerLiteral(1, 2147483647));
    }

    public function testLongIntegerLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->longIntegerLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->longIntegerLiteral(0, 2147483647));
    }

    public function testUnsignedBigIntLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->unsignedBigIntLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->unsignedBigIntLiteral(1, 20));
    }

    public function testDecimalLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->decimalLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->decimalLiteral(10, 2));
    }

    public function testFloatLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->floatLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->floatLiteral(10, 2, -38, 38));
    }

    public function testHexLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->hexLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->hexLiteral(1, 16));
    }

    public function testBinaryLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->binaryLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->binaryLiteral(1, 64));
    }

    public function testHostnameDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->hostname();
        $faker->seed(42);

        self::assertSame($result, $provider->hostname(1, 1, 16));
    }

    public function testQuotedIdentifierCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->quotedIdentifier(5, 10);

        self::assertMatchesRegularExpression('/^`[a-z_][a-z0-9_]{4,9}`$/', $result);
    }

    public function testStringLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->stringLiteral(3, 8);
        $content = substr($result, 1, -1);

        self::assertGreaterThanOrEqual(3, strlen($content));
        self::assertLessThanOrEqual(8, strlen($content));
    }

    public function testNationalStringLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->nationalStringLiteral(2, 5);
        $content = substr($result, 2, -1);

        self::assertGreaterThanOrEqual(2, strlen($content));
        self::assertLessThanOrEqual(5, strlen($content));
    }

    public function testDollarQuotedStringCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->dollarQuotedString(2, 6);
        $content = substr($result, 2, -2);

        self::assertGreaterThanOrEqual(2, strlen($content));
        self::assertLessThanOrEqual(6, strlen($content));
    }

    public function testIntegerLiteralCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->integerLiteral(100, 500);

        self::assertGreaterThanOrEqual(100, (int) $result);
        self::assertLessThanOrEqual(500, (int) $result);
    }

    public function testLongIntegerLiteralCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->longIntegerLiteral(10, 100);

        self::assertGreaterThanOrEqual(10, (int) $result);
        self::assertLessThanOrEqual(100, (int) $result);
    }

    public function testDecimalLiteralCustomPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->decimalLiteral(5, 2);

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteralCustomParams(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->floatLiteral(5, 2, -10, 10);

        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->hexLiteral(4, 8);

        self::assertMatchesRegularExpression('/^0x[0-9a-f]{4,8}$/', $result);
    }

    public function testBinaryLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->binaryLiteral(8, 16);

        self::assertMatchesRegularExpression('/^0b[01]{8,16}$/', $result);
    }

    public function testHostnameCustomParams(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->hostname(2, 3, 5);

        self::assertMatchesRegularExpression('/^[a-z][a-z0-9]{0,4}(\.[a-z][a-z0-9]{0,4}){1,2}$/', $result);
    }

    public function testHostnameSinglePartCustomParams(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->hostname(1, 1, 12);

        self::assertStringNotContainsString('.', $result);
        self::assertMatchesRegularExpression('/^[a-z][a-z0-9]{0,11}$/', $result);
    }

    public function testFilterWildcardPattern(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->filterWildcardPattern();

        self::assertMatchesRegularExpression("/^'[a-z][a-z0-9]{0,11}\\.[a-z][a-z0-9]{0,11}'$/", $result);
    }

    public function testFilterWildcardPatternCustomMaxPartLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->filterWildcardPattern(13);

        self::assertMatchesRegularExpression("/^'[a-z][a-z0-9]{0,12}\\.[a-z][a-z0-9]{0,12}'$/", $result);
    }

    public function testResetMasterIndex(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->resetMasterIndex();

        self::assertMatchesRegularExpression('/^\d+$/', $result);
        self::assertGreaterThanOrEqual(1, (int) $result);
        self::assertLessThanOrEqual(2_000_000_000, (int) $result);
    }

    #[DataProvider('providerDeterministicHelperGeneration')]
    /**
     * @param \Closure(MySqlProvider): string $generate
     */
    public function testDeterministicHelperOutputIsReproducible(\Closure $generate): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);

        $faker->seed(0);
        $first = $generate($provider);

        $faker->seed(0);
        $second = $generate($provider);

        self::assertSame($first, $second);
    }

    /**
     * @return iterable<string, array{\Closure(MySqlProvider): string}>
     */
    public static function providerDeterministicHelperGeneration(): iterable
    {
        yield 'default string literal' => [static fn (MySqlProvider $provider): string => $provider->stringLiteral()];
        yield 'custom string literal' => [static fn (MySqlProvider $provider): string => $provider->stringLiteral(3, 8)];
        yield 'default national string literal' => [static fn (MySqlProvider $provider): string => $provider->nationalStringLiteral()];
        yield 'custom national string literal' => [static fn (MySqlProvider $provider): string => $provider->nationalStringLiteral(2, 5)];
        yield 'default dollar quoted string' => [static fn (MySqlProvider $provider): string => $provider->dollarQuotedString()];
        yield 'custom dollar quoted string' => [static fn (MySqlProvider $provider): string => $provider->dollarQuotedString(2, 6)];
        yield 'default hostname' => [static fn (MySqlProvider $provider): string => $provider->hostname()];
        yield 'custom hostname' => [static fn (MySqlProvider $provider): string => $provider->hostname(2, 3, 5)];
        yield 'custom single-part hostname' => [static fn (MySqlProvider $provider): string => $provider->hostname(1, 1, 12)];
        yield 'default filter wildcard pattern' => [static fn (MySqlProvider $provider): string => $provider->filterWildcardPattern()];
        yield 'custom filter wildcard pattern' => [static fn (MySqlProvider $provider): string => $provider->filterWildcardPattern(13)];
        yield 'reset master index' => [static fn (MySqlProvider $provider): string => $provider->resetMasterIndex()];
    }
}
