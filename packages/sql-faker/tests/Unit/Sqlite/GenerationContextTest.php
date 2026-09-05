<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
use SqlFaker\Sqlite\GenerationContext;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Sqlite\LexicalGrammar;
use SqlFaker\SqliteProvider;

#[CoversClass(GenerationContext::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(Production::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(SqlGenerator::class)]
#[CoversClass(SqliteGrammar::class)]
#[CoversClass(SqliteProvider::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(TerminationAnalyzer::class)]
#[CoversClass(TokenJoiner::class)]
#[Large]
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
#[UsesClass(\SqlFaker\Sqlite\GrammarAdaptation::class)]
#[UsesClass(SqliteGrammar::class)]
#[UsesClass(LexicalGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteTerminalRealizer::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteTokenizer::class)]
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
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT'),
                    new Terminal('foo'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertSame('SELECT foo', $result);
    }

    public function testGenerateUsesProductionConstraintsFromThePlan(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('OTHER')]),
                new Production([new Terminal('EXPECTED')]),
            ]),
        ]);
        $faker = Factory::create();
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $plan = GenerationPlan::constrained('stmt', [
            'stmt' => [ProductionPattern::exactly('EXPECTED')],
        ]);

        self::assertSame('EXPECTED', $generator->generate(($plan)->withStepBudget()));
    }

    public function testGenerateEnforcesThePlansNonEmptyRequirement(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([]),
                new Production([new Terminal('EXPECTED')]),
            ]),
        ]);
        $faker = Factory::create();
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $plan = GenerationPlan::fromRule('stmt')->requiringNonEmpty();

        self::assertSame('EXPECTED', $generator->generate(($plan->withMaxDepth(1))->withStepBudget()));
    }

    public function testGenerateCanDeliberatelyProduceStrictTableOption(): void
    {
        $grammar = new Grammar('table_option', [
            'table_option' => new ProductionRule('table_option', []),
        ]);
        $faker = Factory::create();
        $context = new GenerationContext(
            $grammar,
            $faker,
            'sqlite-3.47.2'
        );
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertStringContainsString('STRICT', $generator->generate((GenerationPlan::fromRule('table_option'))->withStepBudget()));
    }

    public function testVersionBoundGeneratorRejectsAnUnknownGrammarTerminal(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('NOT_A_SQLITE_TERMINAL')]),
            ]),
        ]);
        $faker = Factory::create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NOT_A_SQLITE_TERMINAL');

        $context = new GenerationContext($grammar, $faker, 'sqlite-3.47.2');
        new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
    }

    public function testGenerateDefaultStartRule(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('DEFAULT_RULE_USED')]),
            ]),
            'other_rule' => new ProductionRule('other_rule', [
                new Production([new Terminal('OTHER_RULE_USED')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::all())->withStepBudget());

        self::assertSame('DEFAULT_RULE_USED', $result);
    }

    public function testGenerateResetsBetweenCalls(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('a')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result1 = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());
        $result2 = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertSame('a', $result1);
        self::assertSame('a', $result2);
    }

    public function testGenerateSelectsShortestAlternativeAtTargetDepth(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT'),
                    new NonTerminal('expr'),
                    new Terminal('FROM'),
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
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt')->withMaxDepth(1))->withStepBudget());

        self::assertSame('SHORT', $result);
    }

    public function testGenerateTreatsTargetDepthLessThanOneAsOne(): void
    {
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
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $resultZero = $generator->generate((GenerationPlan::fromRule('stmt')->withMaxDepth(0))->withStepBudget());
        $resultNegative = $generator->generate((GenerationPlan::fromRule('stmt')->withMaxDepth(-10))->withStepBudget());
        $resultOne = $generator->generate((GenerationPlan::fromRule('stmt')->withMaxDepth(1))->withStepBudget());

        self::assertSame('SHORT', $resultZero);
        self::assertSame('SHORT', $resultNegative);
        self::assertSame('SHORT', $resultOne);
    }

    public function testGenerateSelectsFirstAlternativeOnLengthTie(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('FIRST')]),
                new Production([new Terminal('SECOND')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt')->withMaxDepth(1))->withStepBudget());

        self::assertSame('FIRST', $result);
    }

    public function testGenerateSelectsTerminatingAlternativeOnRecursiveLengthTie(): void
    {
        $grammar = new Grammar('value', [
            'value' => new ProductionRule('value', [
                new Production([new NonTerminal('value')]),
                new Production([new Terminal('VALUE')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('VALUE', $generator->generate((GenerationPlan::fromRule('value')->withMaxDepth(1))->withStepBudget()));
    }

    public function testFixedSeedRejectsAProductionThatCannotFitTheDerivationBudget(): void
    {
        $grammar = new Grammar('value', [
            'value' => new ProductionRule('value', [
                new Production(array_fill(0, 5001, new NonTerminal('value'))),
                new Production([new Terminal('VALUE')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('VALUE', $generator->generate((GenerationPlan::fromRule('value'))->withStepBudget()));
    }

    public function testDerivationBudgetIncludesEveryRemainingNonTerminal(): void
    {
        $remaining = array_fill(0, 4998, new NonTerminal('leaf'));
        $grammar = new Grammar('start', [
            'start' => new ProductionRule('start', [
                new Production([new NonTerminal('choice'), ...$remaining]),
            ]),
            'choice' => new ProductionRule('choice', [
                new Production([new NonTerminal('extra')]),
                new Production([new Terminal('EXPECTED')]),
            ]),
            'extra' => new ProductionRule('extra', [
                new Production([new Terminal('UNEXPECTED')]),
            ]),
            'leaf' => new ProductionRule('leaf', [
                new Production([new Terminal('x')]),
            ]),
        ]);
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $faker->seed(3);

        self::assertStringStartsWith('EXPECTED ', $generator->generate((GenerationPlan::fromRule('start'))->withStepBudget()));
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
        $faker1->seed($seed1);
        $context = new GenerationContext($grammar, $faker1);
        $generator1 = new SqlGenerator($context->grammar, $faker1, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $result1 = $generator1->generate((GenerationPlan::fromRule('stmt')->withMaxDepth(PHP_INT_MAX))->withStepBudget());

        $faker2 = Factory::create();
        $faker2->seed($seed2);
        $context = new GenerationContext($grammar, $faker2);
        $generator2 = new SqlGenerator($context->grammar, $faker2, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $result2 = $generator2->generate((GenerationPlan::fromRule('stmt')->withMaxDepth(PHP_INT_MAX))->withStepBudget());

        self::assertNotSame($result1, $result2);
    }

    public function testGenerateSwitchesToShortestSelectionAtExactlyTargetDepth(): void
    {
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
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt')->withMaxDepth(3))->withStepBudget());

        self::assertSame('SHORT', $result);
    }

    public function testGenerateExpandsLeftmostNonTerminalFirst(): void
    {
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
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertSame(['INTEGER', 'ID', 'INTEGER', 'ID'], (new LexicalGrammar($faker, 'sqlite-3.47.2', true))->tokenize($result));
    }

    public function testGenerateWithNestedNonTerminals(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT'),
                    new NonTerminal('expr'),
                ]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new NonTerminal('value')]),
            ]),
            'value' => new ProductionRule('value', [
                new Production([new Terminal('42')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertSame(['SELECT', 'INTEGER'], (new LexicalGrammar($faker, 'sqlite-3.47.2', true))->tokenize($result));
    }

    public function testGenerateWithEmptyProductionSymbols(): void
    {
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
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertSame('A B', $result);
    }

    public function testGenerateAugmentedInsertRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('insert')->withMaxDepth(6))->withStepBudget());

        self::assertNotSame('', $result);
    }

    public function testGenerateAugmentedDeleteRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('delete')->withMaxDepth(6))->withStepBudget());

        self::assertNotSame('', $result);
    }

    public function testGenerateAugmentedUpdateRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('update')->withMaxDepth(6))->withStepBudget());

        self::assertNotSame('', $result);
    }

    public function testGenerateAugmentedDropTableRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('drop_table')->withMaxDepth(6))->withStepBudget());

        self::assertNotSame('', $result);
    }

    public function testGenerateAugmentedAlterTableRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('alter_table')->withMaxDepth(6))->withStepBudget());

        self::assertNotSame('', $result);
    }

    public function testGenerateThrowsOnDerivationLimit(): void
    {
        $grammar = new Grammar('infinite', [
            'infinite' => new ProductionRule('infinite', [
                new Production([
                    new NonTerminal('infinite'),
                    new Terminal('a'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('Grammar rule has no lexically realizable alternative: infinite');

        $generator->generate((GenerationPlan::fromRule('infinite'))->withStepBudget());
    }

    public function testGenerateThrowsOnEmptyAlternatives(): void
    {
        $grammar = new Grammar('empty', [
            'empty' => new ProductionRule('empty', []),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage("Production rule 'empty' has no alternatives.");

        $generator->generate((GenerationPlan::fromRule('empty'))->withStepBudget());
    }

    public function testGenerateUnknownNonTerminalTreatedAsTerminal(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT'),
                    new NonTerminal('unknown_rule'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertSame('SELECT unknown_rule', $result);
    }

    public function testGenerateDefaultTerminalRendersAsIs(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('UNKNOWN_TOKEN')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertSame('UNKNOWN_TOKEN', $result);
    }

    #[DataProvider('providerGenerateSpecialToken')]
    public function testGenerateSpecialToken(string $terminalName, string $expected): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertSame($expected, $result);
    }

    #[DataProvider('providerGenerateKeywordMapping')]
    public function testGenerateKeywordMapping(string $terminalName, string $expected): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertSame($expected, $result);
    }

    #[DataProvider('providerGenerateLexicalToken')]
    public function testGenerateLexicalToken(string $terminalName, string $pattern): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);
        $faker->seed(12345);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertMatchesRegularExpression($pattern, $result);
    }

    #[DataProvider('providerGenerateCompoundKeyword')]
    public function testGenerateCompoundKeyword(string $terminalName, string $pattern): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertMatchesRegularExpression($pattern, $result);
    }

    public function testGenerateSpacingFunctionParen(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('COUNT'),
                    new Terminal('LP'),
                    new Terminal('STAR'),
                    new Terminal('RP'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('COUNT(*)', $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget()));
    }

    public function testGenerateSpacingDot(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                    new Terminal('DOT'),
                    new Terminal('b'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('a.b', $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget()));
    }

    public function testGenerateSpacingBracket(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('['),
                    new Terminal('x'),
                    new Terminal(']'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated SQLite bracket identifier.');

        $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());
    }

    public function testGenerateSpacingCommaNoSpaceBefore(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                    new Terminal('COMMA'),
                    new Terminal('b'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('a, b', $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget()));
    }

    public function testGenerateSpacingSemicolonNoSpaceBefore(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT'),
                    new Terminal('1'),
                    new Terminal('SEMI'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('SELECT 1;', $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget()));
    }

    public function testGenerateSpacingCloseParenNoSpaceBefore(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('x'),
                    new Terminal('RP'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('x)', $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget()));
    }

    public function testGenerateSpacingOpenParenNoSpaceAfter(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('LP'),
                    new Terminal('x'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('(x', $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget()));
    }

    public function testGenerateSpacingArrowNoSpaceAround(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                    new Terminal('PTR'),
                    new Terminal('b'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('a->b', $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget()));
    }

    public function testGenerateSpacingQuotedIdentifierBeforeParen(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('"func"'),
                    new Terminal('LP'),
                    new Terminal('RP'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('"func"()', $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget()));
    }

    public function testGenerateSpacingNonWordBeforeParenHasSpace(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('+'),
                    new Terminal('LP'),
                    new Terminal('x'),
                    new Terminal('RP'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('+ (x)', $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget()));
    }

    public function testGenerateAddsSpaceBetweenTokensByDefault(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('A'),
                    new Terminal('B'),
                    new Terminal('C'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        self::assertSame('A B C', $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget()));
    }

    public function testGenerateTrimsOutput(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('A')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

        self::assertSame('A', $result);
        self::assertSame($result, trim($result));
    }

    public function testGenerateIdentifierQuotesReservedWords(): void
    {
        $reservedWords = ['as', 'by', 'do', 'if', 'in', 'is', 'no', 'of', 'on', 'or', 'to',
            'add', 'all', 'and', 'for', 'key', 'not', 'set'];
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('ID')]),
            ]),
            'keyword_catalog' => new ProductionRule('keyword_catalog', [
                new Production(array_map(
                    static fn (string $keyword): Terminal => new Terminal(strtoupper($keyword)),
                    $reservedWords,
                )),
            ]),
        ]);

        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $context = new GenerationContext($grammar, $faker);
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->normalize, $context->startSymbol);

        $invalid = array_filter(array_map(static function (int $seed) use ($faker, $generator, $reservedWords): ?string {
            $faker->seed($seed);
            $result = $generator->generate((GenerationPlan::fromRule('stmt'))->withStepBudget());

            $bare = strtolower(trim($result, '"`[]'));
            if (!in_array($bare, $reservedWords, true)) {
                return null;
            }

            return preg_match('/^(?:"(?:""|[^"])*"|`(?:``|[^`])*`|\[[^]]*])$/', $result) === 1
                ? null
                : "Seed {$seed}: {$result}";
        }, range(0, 9999)));

        self::assertSame([], $invalid);
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
    public static function providerGenerateSpecialToken(): iterable
    {
        yield 'LP' => ['LP', '('];
        yield 'RP' => ['RP', ')'];
        yield 'SEMI' => ['SEMI', ';'];
        yield 'COMMA' => ['COMMA', ','];
        yield 'DOT' => ['DOT', '.'];
        yield 'STAR' => ['STAR', '*'];
        yield 'EQ' => ['EQ', '='];
        yield 'LT' => ['LT', '<'];
        yield 'PLUS' => ['PLUS', '+'];
        yield 'MINUS' => ['MINUS', '-'];
        yield 'BITAND' => ['BITAND', '&'];
        yield 'BITNOT' => ['BITNOT', '~'];
        yield 'CONCAT' => ['CONCAT', '||'];
        yield 'PTR' => ['PTR', '->'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateKeywordMapping(): iterable
    {
        yield 'AUTOINCR' => ['AUTOINCR', 'AUTOINCREMENT'];
        yield 'COLUMNKW' => ['COLUMNKW', 'COLUMN'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateLexicalToken(): iterable
    {
        yield 'ID' => ['ID', '/^(?:[a-z_][a-z0-9_]*|"(?:""|[^"])*"|`(?:``|[^`])*`|\[[^]]*])$/'];
        yield 'id' => ['id', '/^(?:[a-z_][a-z0-9_]*|"(?:""|[^"])*"|`(?:``|[^`])*`|\[[^]]*])$/'];
        yield 'idj' => ['idj', '/^(?:[a-z_][a-z0-9_]*|"(?:""|[^"])*"|`(?:``|[^`])*`|\[[^]]*])$/'];
        yield 'ids' => ['ids', "/^'(?:''|[^'])*'$/s"];
        yield 'STRING' => ['STRING', "/^'(?:''|[^'])*'$/s"];
        yield 'BLOB' => ['BLOB', "/^X'(?:[0-9a-f]{2})*'$/"];
        yield 'INTEGER' => ['INTEGER', '/^\d+$/'];
        yield 'number' => ['number', '/^\d+$/'];
        yield 'QNUMBER' => ['QNUMBER', '/^\d(?:_?\d)*$/'];
        yield 'VARIABLE' => ['VARIABLE', '/^(?:\?\d*|[:@$][A-Za-z_][A-Za-z0-9_]*)$/'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateCompoundKeyword(): iterable
    {
        yield 'JOIN_KW' => ['JOIN_KW', '/^(CROSS|FULL|INNER|LEFT|NATURAL|OUTER|RIGHT)$/'];
        yield 'CTIME_KW' => ['CTIME_KW', '/^(CURRENT_TIME|CURRENT_DATE|CURRENT_TIMESTAMP)$/'];
        yield 'LIKE_KW' => ['LIKE_KW', '/^(LIKE|GLOB)$/'];
    }

    public function testGrammarAndLexicalReleaseAreBoundTogether(): void
    {
        $grammar = new Grammar('start_entry', []);
        $context = new GenerationContext($grammar, Factory::create());

        self::assertSame('cmd', $context->grammar->startSymbol);
        self::assertSame([], $context->grammar->ruleMap);
        self::assertNotSame('', $context->lexicalGrammar->version());
        self::assertNull($context->startSymbol);
        self::assertNull($context->normalize);
    }
    public function testSyntheticTerminalsAreAllowedOnlyWithoutAnExplicitRelease(): void
    {
        $grammar = new Grammar('stmt', []);
        $synthetic = new GenerationContext($grammar, Factory::create());
        $released = new GenerationContext($grammar, Factory::create(), 'sqlite-3.47.2');

        self::assertTrue($synthetic->lexicalGrammar->supports('SYNTHETIC_TEST_TOKEN'));
        self::assertFalse($released->lexicalGrammar->supports('SYNTHETIC_TEST_TOKEN'));
        self::assertSame('sqlite-3.47.2', $released->lexicalGrammar->version());
    }

    public function testExplicitReleaseRejectsAnUncataloguedGrammarTerminal(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SYNTHETIC_TEST_TOKEN')])]),
        ]);
        $this->expectException(LexicalCatalogException::class);

        new GenerationContext($grammar, Factory::create(), 'sqlite-3.47.2');
    }
}
