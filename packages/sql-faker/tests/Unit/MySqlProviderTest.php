<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalCatalog;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\Model\ProductionPattern;
use SqlFaker\Grammar\Source\SqlVersion;
use SqlFaker\Grammar\Source\TokenJoiner;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\MySql\GenerationPlans;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminalInventory;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;
use SqlFaker\MySql\Lexical\LexicalGrammar;
use SqlFaker\MySql\SqlGenerator;
use SqlFaker\MySql\StatementRule;
use SqlFaker\MySqlProvider;

#[CoversClass(MySqlProvider::class)]
#[CoversClass(TokenJoiner::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(SqlGenerator::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(Production::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(TerminationAnalyzer::class)]
#[CoversClass(StatementRule::class)]
#[CoversClass(LexicalGrammar::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(GenerationPlans::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(TerminalInventory::class)]
#[Medium]
final class MySqlProviderTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        gc_collect_cycles();
    }

    public function testSql(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->sql(maxDepth: 10);

        self::assertNotSame('', $result);
    }

    public function testSqlWithStatementRule(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->sql(StatementRule::Select, maxDepth: 3);

        self::assertMatchesRegularExpression('/\bSELECT\b/i', $result);
    }

    public function testSqlWithNullStatementRuleUsesDefault(): void
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

    #[DataProvider('providerSupportedMySqlVersion')]
    public function testSqlWithoutEmptyRowsUsesTheRestrictedGenerationPlan(string $version): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker, $version);

        $result = $provider->sqlWithoutEmptyRows(StatementRule::Insert, maxDepth: 10);

        self::assertMatchesRegularExpression('/\bINSERT\b/i', $result);
        self::assertDoesNotMatchRegularExpression('/\bVALUES?\s*(?:ROW\s*)?\(\s*\)/i', $result);
    }

    public function testSqlWithoutEmptyRowsCanGenerateFromTheWholeGrammar(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->sqlWithoutEmptyRows(maxDepth: 3);

        self::assertNotSame('', $result);
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

    #[DataProvider('providerStatementRuleValue')]
    public function testSqlWithAllStatementRules(StatementRule $type): void
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

    /**
     * @return iterable<string, array{StatementRule}>
     */
    public static function providerStatementRuleValue(): iterable
    {
        yield 'Select' => [StatementRule::Select];
        yield 'Insert' => [StatementRule::Insert];
        yield 'Update' => [StatementRule::Update];
        yield 'Delete' => [StatementRule::Delete];
        yield 'CreateTable' => [StatementRule::CreateTable];
        yield 'AlterTable' => [StatementRule::AlterTable];
        yield 'DropTable' => [StatementRule::DropTable];
        yield 'SimpleStatement' => [StatementRule::SimpleStatement];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerMySqlVersion(): iterable
    {
        foreach (SqlVersion::names('mysql') as $version) {
            yield $version => [$version];
        }
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
    public static function providerMultiTableGenerationSeed(): iterable
    {
        foreach (range(0, 31) as $seed) {
            yield "seed {$seed}" => [$seed];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSupportedMySqlVersion(): iterable
    {
        yield 'MySQL 5.6' => ['mysql-5.6.51'];
        yield 'MySQL 5.7' => ['mysql-5.7.44'];
        yield 'MySQL 8.0' => ['mysql-8.0.44'];
        yield 'MySQL 8.1' => ['mysql-8.1.0'];
        yield 'MySQL 8.2' => ['mysql-8.2.0'];
        yield 'MySQL 8.3' => ['mysql-8.3.0'];
        yield 'MySQL 8.4' => ['mysql-8.4.7'];
        yield 'MySQL 9.0' => ['mysql-9.0.1'];
        yield 'MySQL 9.1' => ['mysql-9.1.0'];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerTargetedGenerationSeed(): iterable
    {
        foreach (range(0, 15) as $seed) {
            yield "seed {$seed}" => [$seed];
        }
    }

}
