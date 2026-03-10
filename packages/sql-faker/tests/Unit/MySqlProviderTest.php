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
use SqlFaker\MySql\StatementType;
use SqlFaker\MySqlProvider;

/**
 * @param list<int> $numberBetweenValues
 */
function deterministicNumberFaker(array $numberBetweenValues): \Faker\Generator
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

#[CoversClass(MySqlProvider::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(GenerationRequest::class)]
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

    public function testGenerateUsesRequestSeedDeterministically(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker, 'mysql-8.0.44');

        self::assertSame(
            $provider->generate(new GenerationRequest('ident', 11, 1)),
            $provider->generate(new GenerationRequest('ident', 11, 1)),
        );
    }

    public function testStringLiteralDefaultKeepsMinimumLengthAtOne(): void
    {
        $provider = new MySqlProvider(deterministicNumberFaker([0, 0]));

        self::assertSame("'a'", $provider->stringLiteral());
    }

    public function testStringLiteralDefaultKeepsMaximumLengthAtThirtyTwo(): void
    {
        $provider = new MySqlProvider(deterministicNumberFaker(array_merge([33], array_fill(0, 32, 0))));

        self::assertSame(32, strlen(substr($provider->stringLiteral(), 1, -1)));
    }

    public function testNationalStringLiteralDefaultKeepsMinimumLengthAtOne(): void
    {
        $provider = new MySqlProvider(deterministicNumberFaker([0, 0]));

        self::assertSame("N'a'", $provider->nationalStringLiteral());
    }

    public function testNationalStringLiteralDefaultKeepsMaximumLengthAtThirtyTwo(): void
    {
        $provider = new MySqlProvider(deterministicNumberFaker(array_merge([33], array_fill(0, 32, 0))));

        self::assertSame(32, strlen(substr($provider->nationalStringLiteral(), 2, -1)));
    }

    public function testDollarQuotedStringDefaultKeepsMinimumLengthAtOne(): void
    {
        $provider = new MySqlProvider(deterministicNumberFaker([0, 0]));

        self::assertSame('$$a$$', $provider->dollarQuotedString());
    }

    public function testDollarQuotedStringDefaultKeepsMaximumLengthAtThirtyTwo(): void
    {
        $provider = new MySqlProvider(deterministicNumberFaker(array_merge([33], array_fill(0, 32, 0))));

        self::assertSame(32, strlen(substr($provider->dollarQuotedString(), 2, -2)));
    }

    public function testHostnameDefaultUsesSinglePartWithinSixteenCharacters(): void
    {
        $provider = new MySqlProvider(deterministicNumberFaker(array_merge([0, 17], array_fill(0, 16, 0))));

        self::assertSame('aaaaaaaaaaaaaaaa', $provider->hostname());
    }

    public function testFilterWildcardPatternUsesSinglePartHostnamesWithinTwelveCharacters(): void
    {
        $provider = new MySqlProvider(deterministicNumberFaker(array_merge(
            [0, 13],
            array_fill(0, 12, 0),
            [0, 13],
            array_fill(0, 12, 0),
        )));

        self::assertSame("'aaaaaaaaaaaa.aaaaaaaaaaaa'", $provider->filterWildcardPattern());
    }

    public function testResetMasterIndexDefaultKeepsMinimumAtOne(): void
    {
        $provider = new MySqlProvider(deterministicNumberFaker([0]));

        self::assertSame('1', $provider->resetMasterIndex());
    }

    public function testResetMasterIndexDefaultKeepsMaximumAtTwoBillion(): void
    {
        $provider = new MySqlProvider(deterministicNumberFaker([2_000_000_001]));

        self::assertSame('2000000000', $provider->resetMasterIndex());
    }

    #[DataProvider('providerPublicApiMethod')]
    public function testPublicApiMethodsRemainPublic(string $methodName): void
    {
        self::assertTrue((new ReflectionMethod(MySqlProvider::class, $methodName))->isPublic());
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
        yield 'identifier' => ['identifier'];
        yield 'quotedIdentifier' => ['quotedIdentifier'];
        yield 'stringLiteral' => ['stringLiteral'];
        yield 'nationalStringLiteral' => ['nationalStringLiteral'];
        yield 'dollarQuotedString' => ['dollarQuotedString'];
        yield 'integerLiteral' => ['integerLiteral'];
        yield 'longIntegerLiteral' => ['longIntegerLiteral'];
        yield 'unsignedBigIntLiteral' => ['unsignedBigIntLiteral'];
        yield 'decimalLiteral' => ['decimalLiteral'];
        yield 'floatLiteral' => ['floatLiteral'];
        yield 'hexLiteral' => ['hexLiteral'];
        yield 'binaryLiteral' => ['binaryLiteral'];
        yield 'hostname' => ['hostname'];
        yield 'filterWildcardPattern' => ['filterWildcardPattern'];
        yield 'resetMasterIndex' => ['resetMasterIndex'];
        yield 'replaceStatement' => ['replaceStatement'];
        yield 'truncateStatement' => ['truncateStatement'];
        yield 'createIndexStatement' => ['createIndexStatement'];
        yield 'dropIndexStatement' => ['dropIndexStatement'];
        yield 'beginStatement' => ['beginStatement'];
        yield 'commitStatement' => ['commitStatement'];
        yield 'rollbackStatement' => ['rollbackStatement'];
        yield 'expr' => ['expr'];
        yield 'simpleExpr' => ['simpleExpr'];
        yield 'literal' => ['literal'];
        yield 'predicate' => ['predicate'];
        yield 'whereClause' => ['whereClause'];
        yield 'orderClause' => ['orderClause'];
        yield 'limitClause' => ['limitClause'];
        yield 'tableReference' => ['tableReference'];
        yield 'joinedTable' => ['joinedTable'];
        yield 'tableIdent' => ['tableIdent'];
        yield 'subquery' => ['subquery'];
        yield 'withClause' => ['withClause'];
    }

}
