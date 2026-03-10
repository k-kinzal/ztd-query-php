<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use Fuzz\Policy\SqliteFuzzPolicy;
use Fuzz\Probe\ProbePhase as FuzzProbePhase;
use Fuzz\Probe\ProbeResult as FuzzProbeResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Spec\Policy\OutcomeKind;
use Spec\Policy\SqlitePolicy;
use Spec\Probe\ProbePhase as SpecProbePhase;
use Spec\Probe\ProbeResult as SpecProbeResult;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Sqlite\StatementType;
use SqlFaker\SqliteProvider;

#[CoversClass(SqliteProvider::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(GenerationRequest::class)]
#[Medium]
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

    public function testSpecPolicyTreatsNearSyntaxErrorAsSyntax(): void
    {
        $kind = (new SqlitePolicy())->classify(
            SpecProbeResult::failed(
                SpecProbePhase::Prepare,
                null,
                null,
                'SQLSTATE[HY000]: General error: 1 near "FROM": syntax error',
            ),
        );

        self::assertSame(OutcomeKind::Syntax, $kind);
    }

    public function testFuzzPolicyTreatsNearSyntaxErrorAsSyntax(): void
    {
        $decision = (new SqliteFuzzPolicy())->classify(
            FuzzProbeResult::failed(
                FuzzProbePhase::Prepare,
                null,
                null,
                'SQLSTATE[HY000]: General error: 1 near "FROM": syntax error',
            ),
        );

        self::assertSame('syntax', $decision->classification);
        self::assertTrue($decision->shouldCrash);
    }

    public function testGenerateUsesRequestSeedDeterministically(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);

        self::assertSame(
            $provider->generate(new GenerationRequest('nm', 17, 1)),
            $provider->generate(new GenerationRequest('nm', 17, 1)),
        );
    }

    public function testGenerateUsesRequestSeedOnTheSameProviderInstance(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);

        $seed0 = $provider->generate(new GenerationRequest('select', 0, 6));
        $seed1 = $provider->generate(new GenerationRequest('select', 1, 6));
        $seed0Again = $provider->generate(new GenerationRequest('select', 0, 6));

        self::assertNotSame($seed0, $seed1);
        self::assertSame($seed0, $seed0Again);
    }

    public function testQuotedIdentifierMatchesPattern(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]*"$/', $provider->quotedIdentifier());
    }

    public function testStringLiteralMatchesPattern(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        self::assertMatchesRegularExpression("/^'[a-zA-Z0-9_]{1,32}'$/", $provider->stringLiteral());
    }

    public function testIntegerLiteralMatchesPattern(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        self::assertMatchesRegularExpression('/^[1-9]\d*$/', $provider->integerLiteral());
    }

    public function testDecimalLiteralMatchesPattern(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $provider->decimalLiteral());
    }

    public function testQuotedIdentifierDefaultMatchesExplicitBounds(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $faker->seed(42);
        $result = $provider->quotedIdentifier();
        $faker->seed(42);

        self::assertSame($result, $provider->quotedIdentifier(1, 128));
    }

    public function testStringLiteralDefaultMatchesExplicitBounds(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $faker->seed(42);
        $result = $provider->stringLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->stringLiteral(1, 32));
    }

    public function testStringLiteralDefaultKeepsMinimumLengthAtOne(): void
    {
        $faker = new class ([0, 0]) extends \Faker\Generator {
            /** @var list<int> */
            private array $numberBetweenValues;

            /**
             * @param list<int> $numberBetweenValues
             */
            public function __construct(array $numberBetweenValues)
            {
                parent::__construct();
                $this->numberBetweenValues = $numberBetweenValues;
            }

            /**
             * @param mixed $int1
             * @param mixed $int2
             */
            #[\Override]
            public function numberBetween($int1 = 0, $int2 = 2147483647): int
            {
                $next = array_shift($this->numberBetweenValues);
                $lower = is_int($int1) ? $int1 : 0;
                $upper = is_int($int2) ? $int2 : 2147483647;
                $value = is_int($next) ? $next : min($lower, $upper);
                $min = min($lower, $upper);
                $max = max($lower, $upper);

                return max($min, min($max, $value));
            }
        };
        $provider = new SqliteProvider($faker);

        self::assertSame("'a'", $provider->stringLiteral());
    }

    public function testStringLiteralDefaultKeepsMaximumLengthAtThirtyTwo(): void
    {
        $faker = new class (array_merge([32], array_fill(0, 32, 0))) extends \Faker\Generator {
            /** @var list<int> */
            private array $numberBetweenValues;

            /**
             * @param list<int> $numberBetweenValues
             */
            public function __construct(array $numberBetweenValues)
            {
                parent::__construct();
                $this->numberBetweenValues = $numberBetweenValues;
            }

            /**
             * @param mixed $int1
             * @param mixed $int2
             */
            #[\Override]
            public function numberBetween($int1 = 0, $int2 = 2147483647): int
            {
                $next = array_shift($this->numberBetweenValues);
                $lower = is_int($int1) ? $int1 : 0;
                $upper = is_int($int2) ? $int2 : 2147483647;
                $value = is_int($next) ? $next : min($lower, $upper);
                $min = min($lower, $upper);
                $max = max($lower, $upper);

                return max($min, min($max, $value));
            }
        };
        $provider = new SqliteProvider($faker);

        self::assertSame(32, strlen(substr($provider->stringLiteral(), 1, -1)));
    }

    public function testStringLiteralDefaultRejectsLengthsAboveThirtyTwo(): void
    {
        $faker = new class (array_merge([33], array_fill(0, 32, 0))) extends \Faker\Generator {
            /** @var list<int> */
            private array $numberBetweenValues;

            /**
             * @param list<int> $numberBetweenValues
             */
            public function __construct(array $numberBetweenValues)
            {
                parent::__construct();
                $this->numberBetweenValues = $numberBetweenValues;
            }

            /**
             * @param mixed $int1
             * @param mixed $int2
             */
            #[\Override]
            public function numberBetween($int1 = 0, $int2 = 2147483647): int
            {
                $next = array_shift($this->numberBetweenValues);
                $lower = is_int($int1) ? $int1 : 0;
                $upper = is_int($int2) ? $int2 : 2147483647;
                $value = is_int($next) ? $next : min($lower, $upper);
                $min = min($lower, $upper);
                $max = max($lower, $upper);

                return max($min, min($max, $value));
            }
        };
        $provider = new SqliteProvider($faker);

        self::assertSame(32, strlen(substr($provider->stringLiteral(), 1, -1)));
    }

    public function testIntegerLiteralDefaultMatchesExplicitBounds(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $faker->seed(42);
        $result = $provider->integerLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->integerLiteral(1, PHP_INT_MAX));
    }

    public function testDecimalLiteralDefaultMatchesExplicitBounds(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $faker->seed(42);
        $result = $provider->decimalLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->decimalLiteral(15, 2));
    }

    public function testQuotedIdentifierCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]{4,9}"$/', $provider->quotedIdentifier(5, 10));
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

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $provider->decimalLiteral(5, 2));
    }

    #[DataProvider('providerPublicApiMethod')]
    public function testPublicApiMethodsRemainPublic(string $methodName): void
    {
        self::assertTrue((new ReflectionMethod(SqliteProvider::class, $methodName))->isPublic());
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
     * @return iterable<string, array{string}>
     */
    public static function providerPublicApiMethod(): iterable
    {
        yield 'selectStatement' => ['selectStatement'];
        yield 'insertStatement' => ['insertStatement'];
        yield 'updateStatement' => ['updateStatement'];
        yield 'deleteStatement' => ['deleteStatement'];
        yield 'createTableStatement' => ['createTableStatement'];
        yield 'alterTableStatement' => ['alterTableStatement'];
        yield 'dropTableStatement' => ['dropTableStatement'];
        yield 'simpleStatement' => ['simpleStatement'];
        yield 'expr' => ['expr'];
        yield 'term' => ['term'];
        yield 'whereClause' => ['whereClause'];
        yield 'orderByClause' => ['orderByClause'];
        yield 'limitClause' => ['limitClause'];
        yield 'groupByClause' => ['groupByClause'];
        yield 'havingClause' => ['havingClause'];
        yield 'fullname' => ['fullname'];
        yield 'withClause' => ['withClause'];
        yield 'identifier' => ['identifier'];
        yield 'quotedIdentifier' => ['quotedIdentifier'];
        yield 'stringLiteral' => ['stringLiteral'];
        yield 'integerLiteral' => ['integerLiteral'];
        yield 'decimalLiteral' => ['decimalLiteral'];
    }
}
