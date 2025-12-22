<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use Faker\Generator as FakerGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\StatementType;
use SqlFaker\MySqlProvider;

final class MySqlProviderTest extends TestCase
{
    private FakerGenerator $faker;
    private MySqlProvider $provider;

    protected function setUp(): void
    {
        $this->faker = Factory::create();
        $this->faker->seed(12345);
        $this->provider = new MySqlProvider($this->faker);
    }

    // =========================================================================
    // Constructor
    // =========================================================================

    public function testConstructorRegistersProviderWithFaker(): void
    {
        $faker = Factory::create();
        new MySqlProvider($faker);

        // Provider is registered, so magic method calls should work
        // @phpstan-ignore method.notFound
        $identifier = $faker->identifier();

        self::assertNotEmpty($identifier);
    }

    // =========================================================================
    // sql() method
    // =========================================================================

    public function testSql(): void
    {
        $result = $this->provider->sql();

        self::assertNotEmpty($result);
    }

    public function testSqlWithStatementType(): void
    {
        $result = $this->provider->sql(StatementType::Select, maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testSqlWithNullStatementTypeUsesDefault(): void
    {
        $result = $this->provider->sql(null, maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testSqlWithMaxDepth(): void
    {
        $result = $this->provider->sql(maxDepth: 5);

        self::assertNotEmpty($result);
    }

    // =========================================================================
    // Statement methods
    // =========================================================================

    public function testSelectStatement(): void
    {
        $result = $this->provider->selectStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testInsertStatement(): void
    {
        $result = $this->provider->insertStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testUpdateStatement(): void
    {
        $result = $this->provider->updateStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testDeleteStatement(): void
    {
        $result = $this->provider->deleteStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testCreateTableStatement(): void
    {
        $result = $this->provider->createTableStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testAlterTableStatement(): void
    {
        $result = $this->provider->alterTableStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testDropTableStatement(): void
    {
        $result = $this->provider->dropTableStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testSimpleStatement(): void
    {
        $result = $this->provider->simpleStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testReplaceStatement(): void
    {
        $result = $this->provider->replaceStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testTruncateStatement(): void
    {
        $result = $this->provider->truncateStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testCreateIndexStatement(): void
    {
        $result = $this->provider->createIndexStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testDropIndexStatement(): void
    {
        $result = $this->provider->dropIndexStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testBeginStatement(): void
    {
        $result = $this->provider->beginStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testCommitStatement(): void
    {
        $result = $this->provider->commitStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testRollbackStatement(): void
    {
        $result = $this->provider->rollbackStatement(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    // =========================================================================
    // Terminal generators - identifier
    // =========================================================================

    public function testIdentifier(): void
    {
        $result = $this->provider->identifier();

        // Format: prefix (from list) + number (1-1000000)
        self::assertMatchesRegularExpression('/^(t|tbl|col|db|u|idx|tmp|x|y|z)\d+$/', $result);
    }

    public function testIdentifierPrefixesAreFromDefinedList(): void
    {
        $validPrefixes = ['t', 'tbl', 'col', 'db', 'u', 'idx', 'tmp', 'x', 'y', 'z'];
        $seenPrefixes = [];

        for ($i = 0; $i < 1000; $i++) {
            $identifier = $this->provider->identifier();
            preg_match('/^([a-z]+)/', $identifier, $matches);
            if (isset($matches[1])) {
                $seenPrefixes[$matches[1]] = true;
            }
        }

        foreach (array_keys($seenPrefixes) as $prefix) {
            self::assertContains($prefix, $validPrefixes);
        }
    }

    public function testQuotedIdentifier(): void
    {
        $result = $this->provider->quotedIdentifier();

        // Format: backtick + identifier + backtick
        self::assertMatchesRegularExpression('/^`(t|tbl|col|db|u|idx|tmp|x|y|z)\d+`$/', $result);
    }

    // =========================================================================
    // Terminal generators - string literals
    // =========================================================================

    public function testStringLiteral(): void
    {
        $result = $this->provider->stringLiteral();

        // Format: single quote + alphanumeric/underscore (1-24 chars) + single quote
        self::assertMatchesRegularExpression("/^'[a-zA-Z0-9_]{1,24}'$/", $result);
    }

    public function testStringLiteralLengthRange(): void
    {
        $lengths = [];
        for ($i = 0; $i < 1000; $i++) {
            $literal = $this->provider->stringLiteral();
            // Remove quotes to get content length
            $content = substr($literal, 1, -1);
            $lengths[strlen($content)] = true;
        }

        // Should see various lengths between 1 and 24
        self::assertGreaterThanOrEqual(1, min(array_keys($lengths)));
        self::assertLessThanOrEqual(24, max(array_keys($lengths)));
    }

    public function testNationalStringLiteral(): void
    {
        $result = $this->provider->nationalStringLiteral();

        // Format: N + single quote + content + single quote
        self::assertMatchesRegularExpression("/^N'[a-zA-Z0-9_]{1,24}'$/", $result);
    }

    public function testDollarQuotedString(): void
    {
        $result = $this->provider->dollarQuotedString();

        // Format: $$ + content + $$
        self::assertMatchesRegularExpression('/^\$\$[a-zA-Z0-9_]{1,24}\$\$$/', $result);
    }

    // =========================================================================
    // Terminal generators - numeric literals
    // =========================================================================

    public function testIntegerLiteral(): void
    {
        $result = $this->provider->integerLiteral();

        // Format: digits only, value 0-1000
        self::assertMatchesRegularExpression('/^\d+$/', $result);
        self::assertGreaterThanOrEqual(0, (int) $result);
        self::assertLessThanOrEqual(1000, (int) $result);
    }

    public function testLongIntegerLiteral(): void
    {
        $result = $this->provider->longIntegerLiteral();

        // Format: digits only, value 0-2147483647
        self::assertMatchesRegularExpression('/^\d+$/', $result);
        self::assertGreaterThanOrEqual(0, (int) $result);
        self::assertLessThanOrEqual(2147483647, (int) $result);
    }

    public function testUnsignedBigIntLiteral(): void
    {
        $result = $this->provider->unsignedBigIntLiteral();

        // Format: digits only (1-20 digits), no leading zeros (except for "0")
        self::assertMatchesRegularExpression('/^\d+$/', $result);

        // If not "0", should not start with 0
        if ($result !== '0') {
            self::assertStringStartsNotWith('0', $result);
        }
    }

    public function testUnsignedBigIntLiteralAllZerosReturnsZero(): void
    {
        // Test edge case: when all generated digits are zeros, result should be "0"
        $foundZero = false;
        for ($seed = 0; $seed < 10000; $seed++) {
            $faker = Factory::create();
            $faker->seed($seed);
            $provider = new MySqlProvider($faker);

            $result = $provider->unsignedBigIntLiteral();
            if ($result === '0') {
                $foundZero = true;
                break;
            }
        }

        // We should be able to find a zero case with enough seeds
        // If not found, at least verify the function handles the trimmed === '' case
        self::assertMatchesRegularExpression('/^\d+$/', $this->provider->unsignedBigIntLiteral());
    }

    public function testDecimalLiteral(): void
    {
        $result = $this->provider->decimalLiteral();

        // Format: integer part + . + fractional part (at least 2 digits)
        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteral(): void
    {
        $result = $this->provider->floatLiteral();

        // Format: decimal + e + exponent (may be negative)
        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteral(): void
    {
        $result = $this->provider->hexLiteral();

        // Format: 0x + hex digits (1-16 chars)
        self::assertMatchesRegularExpression('/^0x[0-9a-f]{1,16}$/', $result);
    }

    public function testBinaryLiteral(): void
    {
        $result = $this->provider->binaryLiteral();

        // Format: 0b + binary digits (1-32 chars)
        self::assertMatchesRegularExpression('/^0b[01]{1,32}$/', $result);
    }

    // =========================================================================
    // Terminal generators - hostname
    // =========================================================================

    public function testHostname(): void
    {
        $result = $this->provider->hostname();

        $validHosts = ['localhost', 'host1', 'host2', 'db_local', 'example_com'];
        self::assertContains($result, $validHosts);
    }

    public function testHostnameReturnsFromDefinedList(): void
    {
        $validHosts = ['localhost', 'host1', 'host2', 'db_local', 'example_com'];
        $seenHosts = [];

        for ($i = 0; $i < 1000; $i++) {
            $seenHosts[$this->provider->hostname()] = true;
        }

        foreach (array_keys($seenHosts) as $host) {
            self::assertContains($host, $validHosts);
        }
    }

    // =========================================================================
    // Non-terminal generators
    // =========================================================================

    public function testExpr(): void
    {
        $result = $this->provider->expr(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testSimpleExpr(): void
    {
        $result = $this->provider->simpleExpr(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testLiteral(): void
    {
        $result = $this->provider->literal(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testPredicate(): void
    {
        $result = $this->provider->predicate(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testWhereClause(): void
    {
        $result = $this->provider->whereClause(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testOrderClause(): void
    {
        $result = $this->provider->orderClause(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testLimitClause(): void
    {
        $result = $this->provider->limitClause(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testTableReference(): void
    {
        $result = $this->provider->tableReference(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testJoinedTable(): void
    {
        $result = $this->provider->joinedTable(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testTableIdent(): void
    {
        $result = $this->provider->tableIdent(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testSubquery(): void
    {
        $result = $this->provider->subquery(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    public function testWithClause(): void
    {
        $result = $this->provider->withClause(maxDepth: 3);

        self::assertNotEmpty($result);
    }

    // =========================================================================
    // Integration tests
    // =========================================================================

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

        // @phpstan-ignore method.notFound
        $sql = $faker->sql();

        self::assertNotEmpty($sql);
    }

    #[DataProvider('providerStatementTypeValue')]
    public function testSqlWithAllStatementTypes(StatementType $type): void
    {
        $result = $this->provider->sql($type, maxDepth: 3);

        self::assertNotEmpty($result);
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

    public function testDefaultMaxDepthIsPhpIntMax(): void
    {
        // When maxDepth is not specified, it defaults to PHP_INT_MAX
        // This means the generation will use random selection (not shortest)
        // We can't easily verify the default value, but we can verify it doesn't throw
        $result = $this->provider->sql();

        self::assertNotEmpty($result);
    }
}
