<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use Faker\Generator;
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
use SqlFaker\Sqlite\GenerationPlans;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Sqlite\LexicalGrammar;
use SqlFaker\Sqlite\SqlGenerator;
use SqlFaker\Sqlite\StatementRule;
use SqlFaker\SqliteProvider;
use UnexpectedValueException;

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
#[CoversClass(StatementRule::class)]
#[CoversClass(LexicalGrammar::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(TerminalInventory::class)]
#[UsesClass(GenerationPlans::class)]
#[Medium]
final class SqliteProviderTest extends TestCase
{
    #[DataProvider('providerTargetedGenerationSeed')]
    public function testInsertFunctionUpsertStatementDerivesFunctionExpressionFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new SqliteProvider($faker);
        $sql = $provider->insertFunctionUpsertStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'sqlite-3.47.2', true))
            ->tokenize($sql);
        $values = array_search('VALUES', $tokens, true);
        $conflict = array_search('CONFLICT', $tokens, true);
        $set = array_search('SET', $tokens, true);
        $functionOpen = array_search('LP', array_slice($tokens, (int) $set, null, true), true);

        self::assertSame($sql, $provider->insertFunctionUpsertStatement(40));
        self::assertSame('INSERT', $tokens[0]);
        self::assertIsInt($values);
        self::assertIsInt($conflict);
        self::assertIsInt($set);
        self::assertIsInt($functionOpen);
        self::assertLessThan($conflict, $values);
        self::assertGreaterThan($set, $functionOpen);
        self::assertContains('UPDATE', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testMultiDmlStatementDerivesTwoStatementBatchFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new SqliteProvider($faker);
        $sql = $provider->multiDmlStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'sqlite-3.47.2', true))->tokenize($sql);
        $separator = array_search('SEMI', $tokens, true);

        self::assertSame($sql, $provider->multiDmlStatement(40));
        self::assertIsInt($separator);
        self::assertContains($tokens[0], ['INSERT', 'UPDATE', 'DELETE']);
        self::assertContains($tokens[$separator + 1], ['INSERT', 'UPDATE', 'DELETE']);
        self::assertSame(2, count(array_filter($tokens, static fn (string $token): bool => $token === 'SEMI')));
    }

    #[DataProvider('providerMultiDmlSelection')]
    public function testMultiDmlStatementCanSelectEveryDmlFamily(
        int $firstChoice,
        int $secondChoice,
        string $first,
        string $second,
    ): void {
        $generator = new class ([$firstChoice, $secondChoice]) extends Generator {
            /** @var list<int> */
            private readonly array $choices;
            private int $call = 0;

            /** @param list<int> $choices */
            public function __construct(array $choices)
            {
                parent::__construct();
                $this->choices = $choices;
            }

            /**
             * @param mixed $min
             * @param mixed $max
             *
             * @throws UnexpectedValueException When the bound is not an integer
             */
            #[Override]
            public function numberBetween($min = 0, $max = 2147483647): int
            {
                if ($this->call < count($this->choices)) {
                    $choice = $this->choices[$this->call];
                    ++$this->call;
                    if ($min !== 0 || $max !== 2 || $choice < $min || $choice > $max) {
                        throw new UnexpectedValueException();
                    }

                    return $choice;
                }
                if (!is_int($min)) {
                    throw new UnexpectedValueException();
                }

                return $min;
            }
        };
        $sql = (new SqliteProvider($generator))->multiDmlStatement();
        $tokens = (new LexicalGrammar(Factory::create(), 'sqlite-3.47.2', true))->tokenize($sql);
        $separator = array_search('SEMI', $tokens, true);

        self::assertIsInt($separator);
        self::assertSame($first, $tokens[0]);
        self::assertSame($second, $tokens[$separator + 1]);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testFullTextSearchStatementDerivesMatchExpressionFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new SqliteProvider($faker);
        $sql = $provider->fullTextSearchStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'sqlite-3.47.2', true))
            ->tokenize($sql);
        $where = array_search('WHERE', $tokens, true);
        $match = array_search('MATCH', $tokens, true);

        self::assertSame($sql, $provider->fullTextSearchStatement(40));
        self::assertSame('SELECT', $tokens[0]);
        self::assertContains('FROM', $tokens);
        self::assertIsInt($where);
        self::assertIsInt($match);
        self::assertGreaterThan($where, $match);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testTemporaryTableStatement(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new SqliteProvider($faker);
        $sql = $provider->temporaryTableStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'sqlite-3.47.2', true))
            ->tokenize($sql);

        self::assertSame($sql, $provider->temporaryTableStatement(40));
        self::assertSame('CREATE', $tokens[0]);
        self::assertContains('TEMP', $tokens);
        self::assertContains('TABLE', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testViewStatement(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new SqliteProvider($faker);
        $sql = $provider->viewStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'sqlite-3.47.2', true))
            ->tokenize($sql);

        self::assertSame($sql, $provider->viewStatement(40));
        self::assertSame('CREATE', $tokens[0]);
        self::assertContains('VIEW', $tokens);
        self::assertContains('SELECT', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testGeneratedColumnStatement(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new SqliteProvider($faker);
        $sql = $provider->generatedColumnStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'sqlite-3.47.2', true))
            ->tokenize($sql);

        self::assertSame($sql, $provider->generatedColumnStatement(40));
        self::assertContains('GENERATED', $tokens);
        self::assertContains('ALWAYS', $tokens);
        self::assertContains('AS', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testForeignKeyCascadeStatement(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new SqliteProvider($faker);
        $sql = $provider->foreignKeyCascadeStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'sqlite-3.47.2', true))
            ->tokenize($sql);

        self::assertSame($sql, $provider->foreignKeyCascadeStatement(40));
        self::assertContains('FOREIGN', $tokens);
        self::assertContains('REFERENCES', $tokens);
        self::assertStringContainsString('ON UPDATE CASCADE', implode(' ', $tokens));
        self::assertStringContainsString('ON DELETE CASCADE', implode(' ', $tokens));
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        gc_collect_cycles();
    }

    public function testRegistersItselfWithTheFakerGenerator(): void
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

    public function testSqlWithStatementRule(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->sql(StatementRule::Select, maxDepth: 6);

        self::assertTrue(
            str_contains($result, 'SELECT') || str_contains($result, 'VALUES'),
            "SelectStmt should produce SELECT or VALUES: {$result}"
        );
    }

    public function testSqlWithNullStatementRuleUsesRandom(): void
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

    public function testForeignKeyConstraint(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker, 'sqlite-3.47.2');

        $result = $provider->foreignKeyConstraint(1);

        $tokens = array_map(
            static fn (string $token): string => in_array($token, ['ID', 'STRING'], true) ? 'NAME' : $token,
            (new LexicalGrammar($faker, 'sqlite-3.47.2'))->tokenize($result),
        );

        self::assertSame(
            ['CONSTRAINT', 'NAME', 'FOREIGN', 'KEY', 'LP', 'NAME', 'RP', 'REFERENCES', 'NAME', 'LP', 'NAME', 'RP'],
            $tokens,
        );

        $faker->seed(2);
        $result = $provider->foreignKeyConstraint(20);

        $tokens = array_map(
            static fn (string $token): string => in_array($token, ['ID', 'STRING'], true) ? 'NAME' : $token,
            (new LexicalGrammar($faker, 'sqlite-3.47.2'))->tokenize($result),
        );

        self::assertSame(
            ['CONSTRAINT', 'NAME', 'FOREIGN', 'KEY'],
            array_slice($tokens, 0, 4),
        );
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

    #[DataProvider('providerStatementRuleValue')]
    public function testSqlWithAllStatementRules(StatementRule $type): void
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

        self::assertSame(
            ['DROP', 'TABLE'],
            array_slice((new LexicalGrammar(Factory::create(), 'sqlite-3.47.2'))->tokenize($sql), 0, 2),
            "DROP TABLE must be present: {$sql}",
        );
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
     * @return iterable<string, array{int}>
     */
    public static function providerTargetedGenerationSeed(): iterable
    {
        foreach (range(0, 31) as $seed) {
            yield "seed {$seed}" => [$seed];
        }
    }

    /**
     * @return iterable<string, array{int, int, string, string}>
     */
    public static function providerMultiDmlSelection(): iterable
    {
        yield 'insert' => [0, 0, 'INSERT', 'INSERT'];
        yield 'update' => [1, 1, 'UPDATE', 'UPDATE'];
        yield 'delete' => [2, 2, 'DELETE', 'DELETE'];
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
    }

}
