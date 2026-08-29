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
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionPattern;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Grammar\Model\TerminalInventory;
use SqlFaker\Grammar\Source\SqlVersion;
use SqlFaker\Grammar\Source\TokenJoiner;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\Grammar\Walk\TerminationAnalyzer;
use SqlFaker\PostgreSql\GenerationPlans;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use SqlFaker\PostgreSql\LexicalGrammar;
use SqlFaker\PostgreSql\SqlGenerator;
use SqlFaker\PostgreSql\StatementRule;
use SqlFaker\PostgreSqlProvider;

#[CoversClass(PostgreSqlProvider::class)]
#[CoversClass(TokenJoiner::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(SqlGenerator::class)]
#[CoversClass(PgGrammar::class)]
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
#[UsesClass(ProductionPattern::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(TerminalInventory::class)]
#[UsesClass(GenerationPlans::class)]
#[Medium]
final class PostgreSqlProviderTest extends TestCase
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
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql(maxDepth: 6);

        self::assertNotSame('', $result);
    }

    public function testSqlWithStatementRule(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql(StatementRule::Select, maxDepth: 6);

        self::assertMatchesRegularExpression('/SELECT|VALUES|TABLE/', $result);
    }

    public function testSqlWithNullStatementRuleUsesRandom(): void
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

    #[DataProvider('providerStatementRuleValue')]
    public function testSqlWithAllStatementRules(StatementRule $type): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql($type, maxDepth: 6);

        self::assertNotSame('', $result);
    }

    public function testGrammarDrivenOutputIsNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql(maxDepth: 8);

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
        yield 'CreateTableAs' => [StatementRule::CreateTableAs];
        yield 'CreateDomain' => [StatementRule::CreateDomain];
        yield 'AlterTable' => [StatementRule::AlterTable];
        yield 'DropTable' => [StatementRule::DropTable];
    }

}
