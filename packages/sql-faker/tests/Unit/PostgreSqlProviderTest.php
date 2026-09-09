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
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\Derivation\TerminationAnalyzer;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\Lexical\TerminalInventory;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\Terminal;
use SqlFaker\PostgreSql\GenerationPlans;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use SqlFaker\PostgreSql\LexicalGrammar;
use SqlFaker\PostgreSql\StatementType;
use SqlFaker\PostgreSqlProvider;

#[CoversClass(PostgreSqlProvider::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(SqlGenerator::class)]
#[CoversClass(PgGrammar::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(Production::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(TerminationAnalyzer::class)]
#[CoversClass(StatementType::class)]
#[CoversClass(LexicalGrammar::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(TerminalInventory::class)]
#[UsesClass(GenerationPlans::class)]
#[Medium]
#[UsesClass(\SqlFaker\Provider\SqlGeneratorFactory::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Derivation::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationTrace::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationCost::class)]
#[UsesClass(\SqlFaker\Grammar\GenerationException::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\SequenceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\SqlSerializer::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\KeywordPhraseSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalMappingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenRewriter::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\UniqueOptionRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalKeywordIndex::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalProfileSource::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RandomCharacters::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\MySql\GenerationContext::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CommonKeywordDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ContextualValueDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\DollarStringDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\KeywordDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\SymbolDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ValueDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\AlterDatabaseRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\ConstraintEnforcementRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\FlushExportRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\InstanceActionRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\IntegerContextRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\LoadSourceCountRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\RequiredAliasRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\RewriteDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\RoleGrantRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\SetNamesRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\VariableSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Grammar\MySqlGrammar::class)]
#[UsesClass(\SqlFaker\MySql\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\MySql\MySqlTokenizer::class)]
#[UsesClass(\SqlFaker\MySql\StartRuleResolver::class)]
#[UsesClass(\SqlFaker\PostgreSql\GenerationContext::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\ContextualNameDefinitions::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\HashBoundLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\KeywordDefinitions::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\ValueDefinitions::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\CopySourceRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\FetchWithTiesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\FunctionNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\HashPartitionBoundRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\LimitOffsetRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\LookaheadRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\OperatorArgumentsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\OverlapsArgumentsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\PublicationObjectRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\RelationNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\RewriteDefinitions::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\TimeZoneIntervalRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgTokenizer::class)]
#[UsesClass(\SqlFaker\Sqlite\GenerationContext::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinModifiers::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\KeywordDefinitions::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\ValueDefinitions::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\IdentifierListRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\JoinRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\RewriteDefinitions::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\StrictTableRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\TableOptionRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\WithoutRowidRule::class)]
#[UsesClass(\SqlFaker\Sqlite\GrammarAdaptation::class)]
#[UsesClass(\SqlFaker\Sqlite\Grammar\SqliteGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteTokenizer::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\AlterEventRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\SubqueryContextRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\ConstraintAttributesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\AnyRelationNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\IndirectionStarRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\IntoClauseRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\TransactionCompletionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\WindowNameLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Alter\OrderByRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\BoundedIntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ReplicationTablePatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\SizeNumberLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\CompoundSelectRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\FunctionArgumentRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Replication\StartRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\SelectOptionsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\TableFunctionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\IdentityOptionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\IntoClauseRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\FieldListRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Replication\TablePatternRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\QuantifiedComparisonRule::class)]
final class PostgreSqlProviderTest extends TestCase
{
    #[DataProvider('providerTargetedGenerationSeed')]
    public function testPartitionOfStatementGeneratesRangeChildDdl(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->partitionOfStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'pg-17.2'))->tokenize($sql);

        self::assertSame($sql, $provider->partitionOfStatement(40));
        self::assertContains('PARTITION', $tokens);
        self::assertContains('FROM', $tokens);
        self::assertContains('TO', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testInsertFunctionUpsertStatementDerivesFunctionExpressionFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->insertFunctionUpsertStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'pg-17.2'))
            ->tokenize($sql);
        $values = array_search('VALUES', $tokens, true);
        $conflict = array_search('CONFLICT', $tokens, true);
        $set = array_search('SET', $tokens, true);
        $functionOpen = array_search('(', array_slice($tokens, (int) $set, null, true), true);

        self::assertSame($sql, $provider->insertFunctionUpsertStatement(40));
        self::assertIsInt($values);
        self::assertIsInt($conflict);
        self::assertIsInt($set);
        self::assertIsInt($functionOpen);
        self::assertLessThan($conflict, $values);
        self::assertGreaterThan($set, $functionOpen);
        self::assertContains('UPDATE', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testPartialIndexUpsertStatementIncludesArbiterPredicate(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->partialIndexUpsertStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2'))
            ->tokenize($sql);

        self::assertSame($sql, $provider->partialIndexUpsertStatement(40));
        $conflict = array_search('CONFLICT', $tokens, true);
        $where = array_search('WHERE', $tokens, true);
        $update = array_search('UPDATE', $tokens, true);
        self::assertIsInt($conflict);
        self::assertIsInt($where);
        self::assertIsInt($update);
        self::assertGreaterThan($conflict, $where);
        self::assertGreaterThan($where, $update);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testFullTextSearchStatementDerivesMatchOperatorFromGrammarAndLexer(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->fullTextSearchStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2'))->tokenize($sql);
        $where = array_search('WHERE', $tokens, true);

        self::assertSame($sql, $provider->fullTextSearchStatement(40));
        self::assertSame('SELECT', $tokens[0]);
        self::assertContains('FROM', $tokens);
        self::assertIsInt($where);
        self::assertSame(['IDENT', 'Op', 'IDENT'], array_slice($tokens, $where + 1, 3));
        self::assertStringContainsString('@@', $sql);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testTemporaryTableStatement(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->temporaryTableStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'pg-17.2'))
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
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->viewStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2'))
            ->tokenize($sql);

        self::assertSame($sql, $provider->viewStatement(40));
        self::assertSame('CREATE', $tokens[0]);
        self::assertContains('VIEW', $tokens);
        self::assertNotSame([], array_intersect(['SELECT', 'VALUES', 'TABLE'], $tokens));
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testGeneratedColumnStatement(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->generatedColumnStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2'))
            ->tokenize($sql);

        self::assertSame($sql, $provider->generatedColumnStatement(40));
        self::assertContains('GENERATED', $tokens);
        self::assertContains('STORED', $tokens);
        self::assertContains('AS', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testForeignKeyCascadeStatement(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->foreignKeyCascadeStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2'))
            ->tokenize($sql);

        self::assertSame($sql, $provider->foreignKeyCascadeStatement(40));
        self::assertContains('FOREIGN', $tokens);
        self::assertContains('REFERENCES', $tokens);
        self::assertStringContainsString('ON UPDATE CASCADE', implode(' ', $tokens));
        self::assertStringContainsString('ON DELETE_P CASCADE', implode(' ', $tokens));
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

    public function testCreateTableAsStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->createTableAsStatement(maxDepth: 8);

        self::assertStringContainsString('CREATE', $result);
        self::assertStringContainsString('TABLE', $result);
        self::assertStringContainsString('AS', $result);
        self::assertMatchesRegularExpression('/\b(?:SELECT|VALUES|TABLE)\b/', $result);
    }

    public function testCreateDomainStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->createDomainStatement(maxDepth: 8);

        self::assertStringContainsString('CREATE', $result);
        self::assertStringContainsString('DOMAIN', $result);
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

        $result = $provider->whereClause(maxDepth: 1);

        self::assertSame('', $result);
    }

    public function testSortClause(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sortClause(maxDepth: 3);

        self::assertSame(['ORDER', 'BY'], array_slice((new LexicalGrammar($faker, 'pg-17.2'))->tokenize($result), 0, 2));
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

    public function testForeignKeyConstraint(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker, 'pg-17.2');

        $result = $provider->foreignKeyConstraint(1);

        self::assertSame(
            ['CONSTRAINT', 'IDENT', 'FOREIGN', 'KEY', '(', 'IDENT', ')', 'REFERENCES', 'IDENT', '(', 'IDENT', ')'],
            (new LexicalGrammar($faker, 'pg-17.2'))->tokenize($result),
        );
    }

    public function testIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->identifier(3);

        self::assertNotSame('', $result);
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

        self::assertMatchesRegularExpression("/^'[a-zA-Z0-9_]{1,255}'$/", $result);
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

        self::assertMatchesRegularExpression('/^\$\$[a-zA-Z0-9_]{1,255}\$\$$/', $result);
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
        self::assertSame($a, $p->stringLiteral(1, 255));
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
        self::assertSame($a, $p->dollarQuotedString(1, 255));
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

    public function testMultipleGenerationsReturnDifferentResults(): void
    {
        $faker1 = Factory::create();
        $faker1->seed(1);
        $provider1 = new PostgreSqlProvider($faker1);
        $sql1 = $provider1->selectStatement(maxDepth: 3);

        $faker2 = Factory::create();
        $faker2->seed(2);
        $provider2 = new PostgreSqlProvider($faker2);
        $sql2 = $provider2->selectStatement(maxDepth: 3);

        self::assertNotSame($sql1, $sql2, 'Different seeds should produce different SQL');
    }

    public function testGrammarDrivenOutputIsNonEmpty(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->sql(maxDepth: 8);

        self::assertNotSame('', $result);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testTableSampleStatementDerivesSamplingClauseFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->tableSampleStatement();
        $faker->seed($seed);

        $tokens = (new LexicalGrammar($faker, 'pg-17.2'))->tokenize($sql);

        self::assertSame($sql, $provider->tableSampleStatement(40));
        self::assertSame('SELECT', $tokens[0]);
        self::assertContains('FROM', $tokens);
        self::assertContains('TABLESAMPLE', $tokens);
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testDoStatementDerivesAnonymousBlockFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->doStatement();
        $faker->seed($seed);

        self::assertSame($sql, $provider->doStatement(40));
        self::assertSame(
            ['DO', 'SCONST'],
            (new LexicalGrammar($faker, 'pg-17.2'))->tokenize($sql),
        );
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testMergeStatementDerivesEveryActionFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->mergeStatement();
        $faker->seed($seed);
        $tokens = (new LexicalGrammar($faker, 'pg-17.2'))->tokenize($sql);
        $normalized = implode(' ', $tokens);
        $delete = strpos($normalized, 'WHEN MATCHED THEN DELETE_P');
        $nothing = strpos($normalized, 'WHEN MATCHED THEN DO NOTHING');
        $update = strpos($normalized, 'WHEN MATCHED THEN UPDATE');
        $insert = strpos($normalized, 'WHEN NOT MATCHED THEN INSERT');

        self::assertSame($sql, $provider->mergeStatement(40));
        self::assertContains('MERGE', $tokens);
        self::assertGreaterThanOrEqual(
            4,
            count(array_filter($tokens, static fn (string $token): bool => $token === 'WHEN')),
        );
        self::assertIsInt($delete);
        self::assertIsInt($nothing);
        self::assertIsInt($update);
        self::assertIsInt($insert);
        self::assertLessThan($nothing, $delete);
        self::assertLessThan($update, $nothing);
        self::assertLessThan($insert, $update);
    }

    public function testCopyStatementUsesOfficialGrammarRule(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        self::assertStringContainsString('COPY', $provider->copyStatement(maxDepth: 8));
    }

    #[DataProvider('providerNullableSimpleStatementSeed')]
    public function testSimpleStatementReturnsNonEmpty(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);

        self::assertNotSame('', $provider->simpleStatement(maxDepth: 20));
    }

    public function testSimpleStatementReturnsNonEmptyAtMinimumDepth(): void
    {
        $faker = Factory::create();
        $faker->seed(0);
        $provider = new PostgreSqlProvider($faker);

        self::assertNotSame('', $provider->simpleStatement(maxDepth: 1));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerNullableSimpleStatementSeed(): iterable
    {
        yield 'PHP 8.1 and 8.2 random mode' => [252];
        yield 'PHP 8.3 and later random mode' => [68];
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
     * @return iterable<string, array{StatementType}>
     */
    public static function providerStatementTypeValue(): iterable
    {
        yield 'Select' => [StatementType::Select];
        yield 'Insert' => [StatementType::Insert];
        yield 'Update' => [StatementType::Update];
        yield 'Delete' => [StatementType::Delete];
        yield 'CreateTable' => [StatementType::CreateTable];
        yield 'CreateTableAs' => [StatementType::CreateTableAs];
        yield 'CreateDomain' => [StatementType::CreateDomain];
        yield 'AlterTable' => [StatementType::AlterTable];
        yield 'DropTable' => [StatementType::DropTable];
    }

    #[DataProvider('providerTargetedGenerationSeed')]
    public function testDomainDmlStatementDerivesDmlFromGrammar(int $seed): void
    {
        $faker = Factory::create();
        $faker->seed($seed);
        $provider = new PostgreSqlProvider($faker);
        $sql = $provider->domainDmlStatement();
        $faker->seed($seed);
        $lexer = new LexicalGrammar($faker, 'pg-17.2');

        $tokens = $lexer->tokenize($sql);

        self::assertSame($sql, $provider->domainDmlStatement(40));
        self::assertContains($tokens[0], ['INSERT', 'UPDATE', 'DELETE_P']);
    }
}
