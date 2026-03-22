<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\PostgreSql\StatementType;
use SqlFaker\PostgreSqlProvider;

/**
 * @param list<int> $numberBetweenValues
 */
function deterministicPostgreSqlNumberFaker(array $numberBetweenValues): \Faker\Generator
{
    return new class ($numberBetweenValues) extends \Faker\Generator {
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
}

#[CoversClass(PostgreSqlProvider::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(GenerationRequest::class)]
#[Medium]
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

    public function testSqlWithNullStatementTypeUsesStmtmultiDefault(): void
    {
        $faker = deterministicPostgreSqlNumberFaker([77]);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql(null, maxDepth: 6);

        self::assertSame(
            $provider->generate(new GenerationRequest('stmtmulti', 77, 6)),
            $result,
        );
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

    public function testGenerateUsesRequestSeedDeterministically(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);

        self::assertSame(
            $provider->generate(new GenerationRequest('ColId', 13, 1)),
            $provider->generate(new GenerationRequest('ColId', 13, 1)),
        );
    }

    #[DataProvider('providerPublicApiMethod')]
    public function testPublicApiMethodsRemainPublic(string $methodName): void
    {
        self::assertTrue((new ReflectionMethod(PostgreSqlProvider::class, $methodName))->isPublic());
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
        yield 'truncateStatement' => ['truncateStatement'];
        yield 'createIndexStatement' => ['createIndexStatement'];
        yield 'transactionStatement' => ['transactionStatement'];
        yield 'expr' => ['expr'];
        yield 'simpleExpr' => ['simpleExpr'];
        yield 'literal' => ['literal'];
        yield 'whereClause' => ['whereClause'];
        yield 'sortClause' => ['sortClause'];
        yield 'selectLimit' => ['selectLimit'];
        yield 'tableRef' => ['tableRef'];
        yield 'joinedTable' => ['joinedTable'];
        yield 'qualifiedName' => ['qualifiedName'];
        yield 'subquery' => ['subquery'];
        yield 'withClause' => ['withClause'];
        yield 'identifier' => ['identifier'];
        yield 'quotedIdentifier' => ['quotedIdentifier'];
        yield 'stringLiteral' => ['stringLiteral'];
        yield 'integerLiteral' => ['integerLiteral'];
        yield 'decimalLiteral' => ['decimalLiteral'];
        yield 'floatLiteral' => ['floatLiteral'];
        yield 'hexLiteral' => ['hexLiteral'];
        yield 'binaryLiteral' => ['binaryLiteral'];
        yield 'dollarQuotedString' => ['dollarQuotedString'];
        yield 'doBodyLiteral' => ['doBodyLiteral'];
        yield 'parameterMarker' => ['parameterMarker'];
    }
}
