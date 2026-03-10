<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\PostgreSql\SqlGenerator;
use SqlFaker\PostgreSql\StatementType;
use SqlFaker\PostgreSqlProvider;

#[CoversClass(PostgreSqlProvider::class)]
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
#[UsesClass(\SqlFaker\PostgreSql\Grammar\PgGrammar::class)]
#[UsesClass(\SqlFaker\Contract\Grammar::class)]
#[UsesClass(\SqlFaker\Contract\ProductionRule::class)]
#[UsesClass(\SqlFaker\Contract\Production::class)]
#[UsesClass(\SqlFaker\Contract\Symbol::class)]
final class PostgreSqlProviderTest extends TestCase
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
        $provider = new PostgreSqlProvider($faker);

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
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql(maxDepth: 6);

        self::assertNotSame('', $result);
    }

    public function testSqlWithStatementType(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql(StatementType::Select, maxDepth: 6);

        self::assertMatchesRegularExpression('/SELECT|VALUES|TABLE/', $result);
    }

    public function testSqlWithNullStatementTypeUsesRandom(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql(null, maxDepth: 6);

        self::assertNotSame('', $result);
    }

    public function testSqlWithMaxDepth(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql(maxDepth: 8);

        self::assertNotSame('', $result);
    }

    public function testSelectStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->selectStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertMatchesRegularExpression('/SELECT|VALUES|TABLE/', $result);
    }

    public function testInsertStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->insertStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('INSERT', $result);
    }

    public function testUpdateStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->updateStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('UPDATE', $result);
    }

    public function testDeleteStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->deleteStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('DELETE', $result);
    }

    public function testCreateTableStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->createTableStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('CREATE', $result);
        self::assertStringContainsString('TABLE', $result);
    }

    public function testAlterTableStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->alterTableStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('ALTER', $result);
    }

    public function testDropTableStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->dropTableStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('DROP', $result);
    }

    public function testTruncateStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->truncateStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('TRUNCATE', $result);
    }

    public function testCreateIndexStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->createIndexStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertStringContainsString('CREATE', $result);
        self::assertStringContainsString('INDEX', $result);
    }

    public function testTransactionStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->transactionStatement(maxDepth: 6);

        self::assertNotEmpty($result);
        self::assertMatchesRegularExpression('/BEGIN|COMMIT|ROLLBACK|ABORT|END|START|SAVEPOINT|RELEASE|PREPARE/', $result);
    }

    public function testExpr(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->expr(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testSimpleExpr(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->simpleExpr(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->literal(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testWhereClause(): void
    {
        $faker = Factory::create();
        $faker->seed(0);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->whereClause(maxDepth: 6);

        self::assertMatchesRegularExpression('/^$|WHERE/', $result);
    }

    public function testSortClause(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sortClause(maxDepth: 3);

        self::assertMatchesRegularExpression('/\bORDER\s+BY\b/', $result);
    }

    public function testSelectLimit(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->selectLimit(maxDepth: 3);

        self::assertMatchesRegularExpression('/LIMIT|OFFSET|FETCH/', $result);
    }

    public function testTableRef(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->tableRef(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testJoinedTable(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->joinedTable(maxDepth: 3);

        self::assertStringContainsString('JOIN', $result);
    }

    public function testQualifiedName(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->qualifiedName(maxDepth: 3);

        self::assertNotSame('', $result);
    }

    public function testSubquery(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->subquery(maxDepth: 3);

        self::assertStringContainsString('(', $result);
        self::assertStringContainsString(')', $result);
    }

    public function testWithClause(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->withClause(maxDepth: 3);

        self::assertStringContainsString('WITH', $result);
    }

    public function testIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->identifier(3);

        self::assertNotSame('', $result);
    }

    #[DataProvider('providerCanonicalIdentifierSeed')]
    public function testIdentifierAvoidsKeywordAlternatives(int $seed): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);

        $faker->seed($seed);

        self::assertDoesNotMatchRegularExpression('/^(VALUES|DELETE|UPDATE|BY|SET|INDEX)$/i', $provider->identifier(3));
    }

    public function testQuotedIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->quotedIdentifier();

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]*"$/', $result);
    }

    public function testStringLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->stringLiteral();

        self::assertMatchesRegularExpression("/^'[a-zA-Z0-9_]{1,32}'$/", $result);
    }

    public function testIntegerLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->integerLiteral();

        self::assertMatchesRegularExpression('/^[1-9]\d*$/', $result);
    }

    public function testDecimalLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->decimalLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->floatLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->hexLiteral();

        self::assertMatchesRegularExpression("/^X'[0-9a-f]{1,16}'$/", $result);
    }

    public function testBinaryLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->binaryLiteral();

        self::assertMatchesRegularExpression("/^B'[01]{1,64}'$/", $result);
    }

    public function testDollarQuotedString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->dollarQuotedString();

        self::assertMatchesRegularExpression('/^\$\$[a-zA-Z0-9_]{1,32}\$\$$/', $result);
    }

    public function testParameterMarker(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->parameterMarker();

        self::assertMatchesRegularExpression('/^\$\d+$/', $result);
    }

    public function testQuotedIdentifierDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $a = $p->quotedIdentifier();
        $faker->seed(42);
        self::assertSame($a, $p->quotedIdentifier(1, 63));
    }

    public function testStringLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $a = $p->stringLiteral();
        $faker->seed(42);
        self::assertSame($a, $p->stringLiteral(1, 32));
    }

    public function testIntegerLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $a = $p->integerLiteral();
        $faker->seed(42);
        self::assertSame($a, $p->integerLiteral(1, 2147483647));
    }

    public function testDecimalLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $a = $p->decimalLiteral();
        $faker->seed(42);
        self::assertSame($a, $p->decimalLiteral(10, 2));
    }

    public function testFloatLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $a = $p->floatLiteral();
        $faker->seed(42);
        self::assertSame($a, $p->floatLiteral(10, 2, -307, 308));
    }

    public function testHexLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $a = $p->hexLiteral();
        $faker->seed(42);
        self::assertSame($a, $p->hexLiteral(1, 16));
    }

    public function testBinaryLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $a = $p->binaryLiteral();
        $faker->seed(42);
        self::assertSame($a, $p->binaryLiteral(1, 64));
    }

    public function testDollarQuotedStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $a = $p->dollarQuotedString();
        $faker->seed(42);
        self::assertSame($a, $p->dollarQuotedString(1, 32));
    }

    public function testParameterMarkerDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $p = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $a = $p->parameterMarker();
        $faker->seed(42);
        self::assertSame($a, $p->parameterMarker(1, 99));
    }

    public function testQuotedIdentifierCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->quotedIdentifier(5, 10);

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]{4,9}"$/', $result);
    }

    public function testStringLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->stringLiteral(3, 8);
        $content = substr($result, 1, -1);

        self::assertGreaterThanOrEqual(3, strlen($content));
        self::assertLessThanOrEqual(8, strlen($content));
    }

    public function testIntegerLiteralCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->integerLiteral(100, 500);

        self::assertGreaterThanOrEqual(100, (int) $result);
        self::assertLessThanOrEqual(500, (int) $result);
    }

    public function testDecimalLiteralCustomPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->decimalLiteral(5, 2);

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteralCustomParams(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->floatLiteral(5, 2, -10, 10);

        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->hexLiteral(4, 8);

        self::assertMatchesRegularExpression("/^X'[0-9a-f]{4,8}'$/", $result);
    }

    public function testBinaryLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->binaryLiteral(8, 16);

        self::assertMatchesRegularExpression("/^B'[01]{8,16}'$/", $result);
    }

    public function testDollarQuotedStringCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->dollarQuotedString(2, 6);
        $content = substr($result, 2, -2);

        self::assertGreaterThanOrEqual(2, strlen($content));
        self::assertLessThanOrEqual(6, strlen($content));
    }

    public function testParameterMarkerCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->parameterMarker(1, 5);

        self::assertMatchesRegularExpression('/^\$[1-5]$/', $result);
    }

    public function testSeededGenerationIsReproducible(): void
    {
        $faker1 = Factory::create();
        $provider1 = new PostgreSqlProvider($faker1);
        $faker1->seed(99999);
        $sql1 = $provider1->sql(maxDepth: 8);

        $faker2 = Factory::create();
        $provider2 = new PostgreSqlProvider($faker2);
        $faker2->seed(99999);
        $sql2 = $provider2->sql(maxDepth: 8);

        self::assertSame($sql1, $sql2, 'Same seed should produce same output');
    }

    #[DataProvider('providerStatementTypeValue')]
    public function testSqlWithAllStatementTypes(StatementType $type): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql($type, maxDepth: 6);

        self::assertNotSame('', $result);
    }

    public function testSelectContainsSelectOrValuesOrTable(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $sql = $provider->selectStatement(maxDepth: 6);

        self::assertMatchesRegularExpression('/SELECT|VALUES|TABLE/', $sql);
    }

    public function testUpdateContainsSetClause(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->updateStatement(maxDepth: 6);

        self::assertStringContainsString('UPDATE', $result);
        self::assertStringContainsString('SET', $result);
    }

    public function testDeleteContainsFromKeyword(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->deleteStatement(maxDepth: 6);

        self::assertStringContainsString('DELETE', $result);
    }

    public function testMultipleGenerationsAreDeterministicForSameSeed(): void
    {
        $faker1 = Factory::create();
        $faker1->seed(1);
        $provider1 = new PostgreSqlProvider($faker1);
        $sql1 = $provider1->selectStatement(maxDepth: 3);

        $faker2 = Factory::create();
        $faker2->seed(1);
        $provider2 = new PostgreSqlProvider($faker2);
        $sql2 = $provider2->selectStatement(maxDepth: 3);

        self::assertSame($sql1, $sql2, 'The same seed should reproduce the same SQL');
    }

    public function testGrammarDrivenOutputIsNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql(maxDepth: 8);

        self::assertNotSame('', $result);
    }

    public function testSimpleStatementReturnsNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        self::assertNotSame('', $provider->simpleStatement(maxDepth: 6));
    }

    public function testRuntimeContractExposesSnapshotSupportedGrammarAndDeterministicGeneration(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);

        self::assertNotSame('', $provider->snapshot()->startSymbol);
        self::assertSame($provider->snapshot()->startSymbol, $provider->supportedGrammar()->startSymbol);
        self::assertNotNull($provider->supportedGrammar()->rule('SelectStmt'));
        self::assertSame(
            $provider->generate(new GenerationRequest('ColId', 13, 1)),
            $provider->generate(new GenerationRequest('ColId', 13, 1)),
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
