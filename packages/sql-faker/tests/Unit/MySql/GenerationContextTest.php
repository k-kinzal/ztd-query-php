<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

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
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\Lexical\TokenJoiner;
use SqlFaker\Grammar\LexicalCatalogException;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\GenerationContext;
use SqlFaker\MySql\GenerationPlans;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\MySql\LexicalGrammar;
use SqlFaker\MySqlProvider;

#[CoversClass(GenerationContext::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(MySqlProvider::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(Production::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(SqlGenerator::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(TerminationAnalyzer::class)]
#[CoversClass(TokenJoiner::class)]
#[Medium]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(LexicalCatalogException::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalCatalog::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalCatalogShape::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalCoverageCheck::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalKeywordIndex::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalProfileSource::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalWitnessCheck::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalWitnessShape::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RandomCharacters::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\SqlVersion::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\TerminalInventory::class)]
#[UsesClass(MySqlGrammar::class)]
#[UsesClass(LexicalGrammar::class)]
#[UsesClass(\SqlFaker\MySql\MySqlTerminalRealizer::class)]
#[UsesClass(\SqlFaker\MySql\MySqlTokenizer::class)]
#[UsesClass(\SqlFaker\MySql\StartRuleResolver::class)]
final class GenerationContextTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        gc_collect_cycles();
    }

    public function testGenerateIsTheOnlyPublicGenerationMethod(): void
    {
        $methods = array_values(array_filter(
            get_class_methods(SqlGenerator::class),
            static fn (string $method): bool => str_starts_with($method, 'generate'),
        ));

        self::assertSame(['generate'], $methods);
    }

    public function testGenerate(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT_SYM'),
                    new Terminal('foo'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('SELECT foo', $result);
    }

    public function testGenerateUsesProductionConstraintsFromThePlan(): void
    {
        $faker = Factory::create();
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('OTHER')]),
                new Production([new Terminal('EXPECTED')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $plan = GenerationPlan::constrained('stmt', [
            'stmt' => [ProductionPattern::exactly('EXPECTED')],
        ]);

        self::assertSame('EXPECTED', $generator->generate($plan));
    }

    public function testGenerateEnforcesThePlansNonEmptyRequirement(): void
    {
        $faker = Factory::create();
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([]),
                new Production([new Terminal('EXPECTED')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $plan = GenerationPlan::fromRule('stmt')->requiringNonEmpty();

        self::assertSame('EXPECTED', $generator->generate($plan->withMaxDepth(1)));
    }

    public function testGeneratePreservesAnEmptyInsertRowAllowedByTheGrammar(): void
    {
        $faker = Factory::create();
        $grammar = new Grammar('insert_stmt', [
            'insert_stmt' => new ProductionRule('insert_stmt', [
                new Production([
                    new Terminal('INSERT_SYM'),
                    new Terminal('INTO'),
                    new Terminal('IDENT'),
                    new Terminal('VALUES'),
                    new NonTerminal('row_value'),
                ]),
            ]),
            'row_value' => new ProductionRule('row_value', [
                new Production([
                    new Terminal('('),
                    new NonTerminal('opt_values'),
                    new Terminal(')'),
                ]),
            ]),
            'opt_values' => new ProductionRule('opt_values', [
                new Production([]),
                new Production([new Terminal('DEFAULT_SYM')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('insert_stmt')->withMaxDepth(1));

        self::assertSame(
            ['INSERT_SYM', 'INTO', 'IDENT', 'VALUES', '(', ')'],
            (new LexicalGrammar($faker, 'mysql-8.4.7', true))->tokenize($result),
        );
    }

    public function testGenerateCanExcludeEveryEmptyRowThroughThePlan(): void
    {
        $faker = Factory::create();
        $grammar = new Grammar('insert_stmt', [
            'insert_stmt' => new ProductionRule('insert_stmt', [
                new Production([
                    new Terminal('INSERT_SYM'),
                    new Terminal('INTO'),
                    new Terminal('IDENT'),
                    new Terminal('VALUES'),
                    new NonTerminal('row_value'),
                    new Terminal(','),
                    new NonTerminal('row_value'),
                ]),
            ]),
            'row_value' => new ProductionRule('row_value', [
                new Production([
                    new Terminal('('),
                    new NonTerminal('opt_values'),
                    new Terminal(')'),
                ]),
            ]),
            'opt_values' => new ProductionRule('opt_values', [
                new Production([]),
                new Production([new Terminal('DEFAULT_SYM')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlans::withoutEmptyRows('insert_stmt')->withMaxDepth(1));

        self::assertSame(
            [
                'INSERT_SYM', 'INTO', 'IDENT', 'VALUES',
                '(', 'DEFAULT_SYM', ')', ',', '(', 'DEFAULT_SYM', ')',
            ],
            (new LexicalGrammar($faker, 'mysql-8.4.7', true))->tokenize($result),
        );
    }

    public function testGenerateUsesSimpleStatementOrBeginAsDefaultStartRule(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('simple_statement_or_begin', [
            'simple_statement_or_begin' => new ProductionRule('simple_statement_or_begin', [
                new Production([new Terminal('DEFAULT_RULE_USED')]),
            ]),
            'other_rule' => new ProductionRule('other_rule', [
                new Production([new Terminal('OTHER_RULE_USED')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::all());

        self::assertSame('DEFAULT_RULE_USED', $result);
    }

    public function testGenerateResetsBetweenCalls(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('a')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result1 = $generator->generate(GenerationPlan::fromRule('stmt'));
        $result2 = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('a', $result1);
        self::assertSame('a', $result2);
    }

    public function testGenerateWithRealGrammar(): void
    {
        $grammar = MySqlGrammar::load();
        $faker = Factory::create();
        $faker->seed(42);
        $provider = new MySqlProvider($faker);

        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $result = $generator->generate(GenerationPlan::fromRule('literal')->withMaxDepth(1));

        self::assertNotSame('', $result);
    }

    public function testGenerateTreatsTargetDepthLessThanOneAsOne(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('A'),
                    new Terminal('B'),
                    new Terminal('C'),
                    new Terminal('D'),
                ]),
                new Production([new Terminal('SHORT')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $resultZero = $generator->generate(GenerationPlan::fromRule('stmt')->withMaxDepth(0));
        $resultNegative = $generator->generate(GenerationPlan::fromRule('stmt')->withMaxDepth(-10));
        $resultOne = $generator->generate(GenerationPlan::fromRule('stmt')->withMaxDepth(1));

        self::assertSame('SHORT', $resultZero);
        self::assertSame('SHORT', $resultNegative);
        self::assertSame('SHORT', $resultOne);
    }

    public function testGenerateSelectsShortestAlternativeAtTargetDepth(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT_SYM'),
                    new NonTerminal('expr'),
                    new Terminal('FROM_SYM'),
                    new NonTerminal('table'),
                ]),
                new Production([new Terminal('SHORT')]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new Terminal('x')]),
            ]),
            'table' => new ProductionRule('table', [
                new Production([new Terminal('t')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt')->withMaxDepth(1));

        self::assertSame('SHORT', $result);
    }

    public function testGenerateSelectsFirstAlternativeOnLengthTie(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('FIRST')]),
                new Production([new Terminal('SECOND')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt')->withMaxDepth(1));

        self::assertSame('FIRST', $result);
    }

    #[DataProvider('providerRandomAlternativeSeeds')]
    public function testGenerateSelectsRandomAlternativeBeforeTargetDepth(int $seed1, int $seed2): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('A')]),
                new Production([new Terminal('B')]),
                new Production([new Terminal('C')]),
            ]),
        ]);

        $faker1 = Factory::create();
        $provider1 = new MySqlProvider($faker1);
        $faker1->seed($seed1);
        $context = new GenerationContext($grammar, $faker1);
        $generator1 = new SqlGenerator($context->grammar, $faker1, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $result1 = $generator1->generate(GenerationPlan::fromRule('stmt')->withMaxDepth(PHP_INT_MAX));

        $faker2 = Factory::create();
        $provider2 = new MySqlProvider($faker2);
        $faker2->seed($seed2);
        $context = new GenerationContext($grammar, $faker2);
        $generator2 = new SqlGenerator($context->grammar, $faker2, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $result2 = $generator2->generate(GenerationPlan::fromRule('stmt')->withMaxDepth(PHP_INT_MAX));

        self::assertNotSame($result1, $result2);
    }

    public function testGenerateSwitchesToShortestSelectionAtExactlyTargetDepth(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('inner')]),
            ]),
            'inner' => new ProductionRule('inner', [
                new Production([new NonTerminal('choice')]),
            ]),
            'choice' => new ProductionRule('choice', [
                new Production([new Terminal('L'), new Terminal('O'), new Terminal('N'), new Terminal('G')]),
                new Production([new Terminal('SHORT')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt')->withMaxDepth(3));

        self::assertSame('SHORT', $result);
    }

    public function testGenerateExpandsLeftmostNonTerminalFirst(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new NonTerminal('first'),
                    new NonTerminal('second'),
                ]),
            ]),
            'first' => new ProductionRule('first', [
                new Production([new Terminal('1ST')]),
            ]),
            'second' => new ProductionRule('second', [
                new Production([new Terminal('2ND')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame(['NUM', 'IDENT', 'NUM', 'IDENT'], (new LexicalGrammar($faker, 'mysql-8.4.7', true))->tokenize($result));
    }

    public function testGenerateWithNestedNonTerminals(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT_SYM'),
                    new NonTerminal('expr'),
                ]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([
                    new NonTerminal('value'),
                ]),
            ]),
            'value' => new ProductionRule('value', [
                new Production([new Terminal('42')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame(['SELECT_SYM', 'NUM'], (new LexicalGrammar($faker, 'mysql-8.4.7', true))->tokenize($result));
    }

    public function testGenerateWithEmptyProductionSymbols(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('A'),
                    new NonTerminal('optional'),
                    new Terminal('B'),
                ]),
            ]),
            'optional' => new ProductionRule('optional', [
                new Production([]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('A B', $result);
    }

    public function testGenerateThrowsAfterExceeding5000DerivationSteps(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('infinite', [
            'infinite' => new ProductionRule('infinite', [
                new Production([
                    new NonTerminal('infinite'),
                    new Terminal('a'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('Grammar rule has no lexically realizable alternative: infinite');

        $generator->generate(GenerationPlan::fromRule('infinite'));
    }

    public function testGenerateThrowsOnEmptyAlternatives(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('empty', [
            'empty' => new ProductionRule('empty', []),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage("Production rule 'empty' has no alternatives.");

        $generator->generate(GenerationPlan::fromRule('empty'));
    }

    public function testGenerateThrowsOnNonExistentRule(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('a')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $this->expectException(GenerationException::class);

        $generator->generate(GenerationPlan::fromRule('non_existent_rule'));
    }

    #[DataProvider('providerGenerateOperator')]
    public function testGenerateOperator(string $terminalName, string $expected): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame($expected, $result);
    }

    #[DataProvider('providerGenerateLexicalToken')]
    public function testGenerateLexicalToken(string $terminalName, string $pattern): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $faker->seed(12345);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertMatchesRegularExpression($pattern, $result);
    }

    public function testGenerateSkipsEndOfInput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT_SYM'),
                    new Terminal('END_OF_INPUT'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('SELECT', $result);
    }

    public function testGenerateRendersWithRollupSymAsMultipleWords(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('WITH_ROLLUP_SYM')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('WITH ROLLUP', $result);
    }

    public function testGenerateStripsSymSuffix(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('SELECT_SYM')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('SELECT', $result);
    }

    public function testGenerateKeepsTerminalWithoutSymSuffix(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal(',')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame(',', $result);
    }

    public function testGenerateReturnsEmptyStringForOnlyEndOfInput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('END_OF_INPUT')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('', $result);
    }

    public function testGenerateAddsSpaceBetweenTokensByDefault(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('A'),
                    new Terminal('B'),
                    new Terminal('C'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('A B C', $result);
    }

    public function testGenerateSingleTokenOutputWithoutSpacing(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('SINGLE')]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('SINGLE', $result);
    }

    public function testGenerateTrimsOutput(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('A'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('A', $result);
        self::assertSame($result, trim($result));
    }

    public function testGenerateNoSpaceBetweenConsecutiveAtSymbols(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('@'),
                    new Terminal('@'),
                    new Terminal('var'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('@@var', $result);
    }

    public function testGenerateNoSpaceBetweenWordAndOpenParen(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('COUNT'),
                    new Terminal('('),
                    new Terminal('*'),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('COUNT(*)', $result);
    }

    #[DataProvider('providerWordBeforeParenNoSpace')]
    public function testGenerateWordBeforeParenNoSpace(string $word): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal($word),
                    new Terminal('('),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame($word . '()', $result);
    }

    #[DataProvider('providerNonWordBeforeParenHasSpace')]
    public function testGenerateNonWordBeforeParenHasSpace(string $word): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal($word),
                    new Terminal('('),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame($word . ' ()', $result);
    }

    public function testGenerateNoSpaceBetweenQuotedIdentifierAndOpenParen(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('`func`'),
                    new Terminal('('),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('`func`()', $result);
    }

    public function testGenerateSpaceBeforeOpenParenWhenPrecededByPartialBacktick(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('`incomplete'),
                    new Terminal('('),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated MySQL quoted token');

        $generator->generate(GenerationPlan::fromRule('stmt'));
    }

    public function testGenerateSpaceBeforeOpenParenWhenPrecededByOperator(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('+'),
                    new Terminal('('),
                    new Terminal('1'),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('+ (1)', $result);
    }

    public function testGenerateSpaceBeforeOpenParenWhenPrecededByNumber(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('123'),
                    new Terminal('('),
                    new Terminal('a'),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('123 (a)', $result);
    }

    public function testGenerateNoSpaceAfterAtSymbol(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('@'),
                    new Terminal('var'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('@var', $result);
    }

    public function testGenerateSanitizesEqualSymBeforeAll(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('EQUAL_SYM'),
                    new Terminal('ALL_SYM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('= ALL', $result);
    }

    public function testGenerateSanitizesEqualSymBeforeAny(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('EQUAL_SYM'),
                    new Terminal('ANY_SYM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('= ANY', $result);
    }

    public function testGenerateKeepsEqualSymBeforeOtherTokens(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('EQUAL_SYM'),
                    new Terminal('NULL_SYM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('<=> NULL', $result);
    }

    public function testGenerateKeepsEqualSymAtEnd(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                    new Terminal('EQUAL_SYM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('a <=>', $result);
    }

    public function testGenerateSanitizesChainBeforeRelease(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('COMMIT_SYM'),
                    new Terminal('AND_SYM'),
                    new Terminal('CHAIN_SYM'),
                    new Terminal('RELEASE_SYM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('COMMIT AND CHAIN', $result);
    }

    public function testGenerateKeepsNoChainBeforeRelease(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('COMMIT_SYM'),
                    new Terminal('AND_SYM'),
                    new Terminal('NO_SYM'),
                    new Terminal('CHAIN_SYM'),
                    new Terminal('RELEASE_SYM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('COMMIT AND NO CHAIN RELEASE', $result);
    }

    public function testGenerateKeepsChainBeforeNoRelease(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('COMMIT_SYM'),
                    new Terminal('AND_SYM'),
                    new Terminal('CHAIN_SYM'),
                    new Terminal('NO_SYM'),
                    new Terminal('RELEASE_SYM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('COMMIT AND CHAIN NO RELEASE', $result);
    }

    public function testGenerateSanitizesFloatAfterColon(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal(':'),
                    new Terminal('FLOAT_NUM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertMatchesRegularExpression('/^: \d+$/', $result);
    }

    public function testGenerateSanitizesDecimalAfterSystem(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SYSTEM_SYM'),
                    new Terminal('DECIMAL_NUM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertMatchesRegularExpression('/^SYSTEM \d+$/', $result);
    }

    public function testGenerateSanitizesDottedIdentBeforeAt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('IDENT'),
                    new Terminal('.'),
                    new Terminal('IDENT'),
                    new Terminal('@'),
                    new Terminal('LEX_HOSTNAME'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertStringNotContainsString('.', explode('@', $result)[0]);
        self::assertStringContainsString('@', $result);
    }

    public function testGenerateKeepsSimpleIdentBeforeAt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('IDENT'),
                    new Terminal('@'),
                    new Terminal('LEX_HOSTNAME'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertStringContainsString('@', $result);
        self::assertStringNotContainsString('.', explode('@', $result)[0]);
    }

    public function testGenerateSanitizesMultipleDottedIdentsBeforeAt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('IDENT'),
                    new Terminal('.'),
                    new Terminal('IDENT'),
                    new Terminal('.'),
                    new Terminal('IDENT'),
                    new Terminal('@'),
                    new Terminal('LEX_HOSTNAME'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertStringNotContainsString('.', explode('@', $result)[0]);
        self::assertStringContainsString('@', $result);
    }

    public function testGenerateSanitizesEmbeddedDotsInTokenBeforeAt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('LEX_HOSTNAME'),
                    new Terminal('@'),
                    new Terminal('LEX_HOSTNAME'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        $parts = explode('@', $result);
        self::assertStringNotContainsString('.', $parts[0]);
        self::assertStringContainsString('@', $result);
    }

    public function testGenerateSanitizesCurrentUserParensBeforeColon(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('CURRENT_USER_SYM'),
                    new Terminal('('),
                    new Terminal(')'),
                    new Terminal(':'),
                    new Terminal('NUM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertStringNotContainsString('(', $result);
        self::assertStringContainsString('CURRENT_USER', $result);
        self::assertStringContainsString(':', $result);
    }

    public function testGenerateKeepsCurrentUserParensWithoutColon(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('CURRENT_USER_SYM'),
                    new Terminal('('),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame('CURRENT_USER()', $result);
    }

    public function testGenerateAppendsEnableToIncompleteAlterEvent(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('ALTER_SYM'),
                    new Terminal('EVENT_SYM'),
                    new Terminal('IDENT'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertStringEndsWith('ENABLE', $result);
    }

    public function testGenerateAppendsEnableToIncompleteAlterEventQualified(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('ALTER_SYM'),
                    new Terminal('EVENT_SYM'),
                    new Terminal('IDENT'),
                    new Terminal('.'),
                    new Terminal('IDENT'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertStringEndsWith('ENABLE', $result);
    }

    public function testGenerateKeepsCompleteAlterEvent(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('ALTER_SYM'),
                    new Terminal('EVENT_SYM'),
                    new Terminal('IDENT'),
                    new Terminal('ENABLE_SYM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertSame(1, substr_count($result, 'ENABLE'));
    }

    public function testGenerateStripsDotsFromLexHostnameWithoutAt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('CREATE_SYM'),
                    new Terminal('ROLE_SYM'),
                    new Terminal('LEX_HOSTNAME'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertStringNotContainsString('.', $result);
        self::assertStringStartsWith('CREATE ROLE ', $result);
    }

    public function testGenerateKeepsLexHostnameBeforeAt(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('IDENT'),
                    new Terminal('@'),
                    new Terminal('LEX_HOSTNAME'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertStringContainsString('@', $result);
    }

    public function testGenerateKeepsFloatInNormalContext(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT_SYM'),
                    new Terminal('FLOAT_NUM'),
                ]),
            ]),
        ]);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate(GenerationPlan::fromRule('stmt'));

        self::assertMatchesRegularExpression('/^SELECT\s+(?:\d+(?:\.\d*)?|\.\d+)[eE][+-]?\d+$/', $result);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function providerRandomAlternativeSeeds(): iterable
    {
        yield 'seeds 0 and 4' => [0, 4];
        yield 'seeds 0 and 7' => [0, 7];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateOperator(): iterable
    {
        yield 'EQ' => ['EQ', '='];
        yield 'EQUAL_SYM' => ['EQUAL_SYM', '<=>'];
        yield 'LT' => ['LT', '<'];
        yield 'GT_SYM' => ['GT_SYM', '>'];
        yield 'LE' => ['LE', '<='];
        yield 'GE' => ['GE', '>='];
        yield 'NE' => ['NE', '<>'];
        yield 'SHIFT_LEFT' => ['SHIFT_LEFT', '<<'];
        yield 'SHIFT_RIGHT' => ['SHIFT_RIGHT', '>>'];
        yield 'AND_AND_SYM' => ['AND_AND_SYM', '&&'];
        yield 'OR2_SYM' => ['OR2_SYM', '||'];
        yield 'OR_OR_SYM' => ['OR_OR_SYM', '||'];
        yield 'NOT2_SYM' => ['NOT2_SYM', 'NOT'];
        yield 'SET_VAR' => ['SET_VAR', ':='];
        yield 'JSON_SEPARATOR_SYM' => ['JSON_SEPARATOR_SYM', '->'];
        yield 'JSON_UNQUOTED_SEPARATOR_SYM' => ['JSON_UNQUOTED_SEPARATOR_SYM', '->>'];
        yield 'NEG' => ['NEG', '-'];
        yield 'PARAM_MARKER' => ['PARAM_MARKER', '?'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateLexicalToken(): iterable
    {
        yield 'IDENT' => ['IDENT', '/^[a-z_][a-z0-9_]*$/'];
        yield 'IDENT_QUOTED' => ['IDENT_QUOTED', '/^`(?:``|[^`])+`$/'];
        yield 'TEXT_STRING' => ['TEXT_STRING', "/^'(?:''|[^'])*'$/s"];
        yield 'NCHAR_STRING' => ['NCHAR_STRING', "/^N'(?:''|[^'])*'$/s"];
        yield 'DOLLAR_QUOTED_STRING_SYM' => ['DOLLAR_QUOTED_STRING_SYM', '/^\$\$.*\$\$$/s'];
        yield 'NUM' => ['NUM', '/^\d+$/'];
        yield 'LONG_NUM' => ['LONG_NUM', '/^\d+$/'];
        yield 'ULONGLONG_NUM' => ['ULONGLONG_NUM', '/^\d+$/'];
        yield 'DECIMAL_NUM' => ['DECIMAL_NUM', '/^(?:\d+\.\d*|\.\d+)$/'];
        yield 'FLOAT_NUM' => ['FLOAT_NUM', '/^(?:\d+(?:\.\d*)?|\.\d+)[eE][+-]?\d+$/'];
        yield 'HEX_NUM' => ['HEX_NUM', "/^(?:0x[0-9a-f]+|X'(?:[0-9a-f]{2})*')$/"];
        yield 'BIN_NUM' => ['BIN_NUM', "/^(?:0b[01]+|B'[01]*')$/"];
        yield 'LEX_HOSTNAME' => ['LEX_HOSTNAME', '/^.+$/'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerWordBeforeParenNoSpace(): iterable
    {
        yield 'single letter' => ['a'];
        yield 'single underscore' => ['_'];
        yield 'underscore with digits' => ['_123'];
        yield 'letter with digit' => ['A1'];
        yield 'mixed case with underscore' => ['myFunc_1'];
        yield 'all uppercase' => ['COUNT'];
        yield 'starts with underscore then letters' => ['_test'];
        yield 'operator at' => ['@'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerNonWordBeforeParenHasSpace(): iterable
    {
        yield 'starts with digit' => ['123abc'];
        yield 'only digits' => ['123'];
        yield 'contains hyphen' => ['my-func'];
        yield 'contains space' => ['my func'];
        yield 'operator plus' => ['+'];
    }

    public function testGrammarAndLexicalReleaseAreBoundTogether(): void
    {
        $grammar = new Grammar('start_entry', []);
        $context = new GenerationContext($grammar, Factory::create());

        self::assertSame('start_entry', $context->grammar->startSymbol);
        self::assertSame([], $context->grammar->ruleMap);
        self::assertNotSame('', $context->lexicalGrammar->version());
        self::assertNotNull($context->startSymbol);
        self::assertNotNull($context->normalize);
    }
    public function testSyntheticTerminalsAreAllowedOnlyWithoutAnExplicitRelease(): void
    {
        $grammar = new Grammar('stmt', []);
        $synthetic = new GenerationContext($grammar, Factory::create());
        $released = new GenerationContext($grammar, Factory::create(), 'mysql-8.4.7');

        self::assertTrue($synthetic->lexicalGrammar->supports('SYNTHETIC_TEST_TOKEN'));
        self::assertFalse($released->lexicalGrammar->supports('SYNTHETIC_TEST_TOKEN'));
        self::assertSame('mysql-8.4.7', $released->lexicalGrammar->version());
    }

    public function testExplicitReleaseRejectsAnUncataloguedGrammarTerminal(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SYNTHETIC_TEST_TOKEN')])]),
        ]);
        $this->expectException(LexicalCatalogException::class);

        new GenerationContext($grammar, Factory::create(), 'mysql-8.4.7');
    }
}
