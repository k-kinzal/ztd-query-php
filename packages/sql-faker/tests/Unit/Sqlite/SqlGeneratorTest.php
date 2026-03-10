<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Spec\Runner\GrammarContractChecker;
use Spec\Support\GrammarClaimLoader;
use Spec\Support\GrammarEvidenceAssert;
use SqlFaker\Contract\Grammar as ContractGrammar;
use SqlFaker\Contract\Production as ContractProduction;
use SqlFaker\Contract\ProductionRule as ContractProductionRule;
use SqlFaker\Contract\Symbol as ContractSymbol;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Sqlite\SqlGenerator;
use SqlFaker\SqliteProvider;

function sqliteSqlGeneratorContractGrammar(): ContractGrammar
{
    $faker = Factory::create();
    $generator = new SqlGenerator(SqliteGrammar::load(), $faker, new SqliteProvider($faker));

    return ContractGrammarProjector::project($generator->compiledGrammar(), NonTerminal::class);
}

#[CoversClass(SqlGenerator::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(SqliteProvider::class)]
#[UsesClass(ContractGrammarProjector::class)]
#[UsesClass(ContractGrammar::class)]
#[UsesClass(ContractProductionRule::class)]
#[UsesClass(ContractProduction::class)]
#[UsesClass(ContractSymbol::class)]
#[Large]
final class SqlGeneratorTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        gc_collect_cycles();
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SELECT foo', $result);
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate();

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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result1 = $generator->generate('stmt');
        $result2 = $generator->generate('stmt');

        self::assertSame('a', $result1);
        self::assertSame('a', $result2);
    }

    public function testGenerateResetsIdentifierOrdinalBetweenCalls(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('ID')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('_i0', $generator->generate('stmt'));
        self::assertSame('_i0', $generator->generate('stmt'));
    }

    /**
     * @param array<string, mixed> $evidence
     */
    #[DataProvider('providerContractGrammarEvidence')]
    public function testCompiledGrammarSatisfiesSqliteContractClaims(array $evidence, string $claimId): void
    {
        $grammar = sqliteSqlGeneratorContractGrammar();

        GrammarEvidenceAssert::assert(
            $grammar,
            new GrammarContractChecker($grammar),
            $evidence,
            $claimId,
        );
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt', 1);

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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $resultZero = $generator->generate('stmt', 0);
        $resultNegative = $generator->generate('stmt', -10);
        $resultOne = $generator->generate('stmt', 1);

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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt', 1);

        self::assertSame('FIRST', $result);
    }

    public function testGenerateConsumesRandomChoicesInLeftmostDerivationOrder(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new NonTerminal('first'),
                    new NonTerminal('second'),
                ]),
            ]),
            'first' => new ProductionRule('first', [
                new Production([new Terminal('A')]),
                new Production([new Terminal('B')]),
            ]),
            'second' => new ProductionRule('second', [
                new Production([new Terminal('1')]),
                new Production([new Terminal('2')]),
            ]),
        ]);
        $faker = new class ([0, 1, 0]) extends \Faker\Generator {
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider(Factory::create()));

        self::assertSame('B 1', $generator->generate('stmt'));
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
        $generator1 = new SqlGenerator($grammar, $faker1, new SqliteProvider($faker1));
        $result1 = $generator1->generate('stmt', PHP_INT_MAX);

        $faker2 = Factory::create();
        $faker2->seed($seed2);
        $generator2 = new SqlGenerator($grammar, $faker2, new SqliteProvider($faker2));
        $result2 = $generator2->generate('stmt', PHP_INT_MAX);

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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt', 3);

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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('1ST 2ND', $result);
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SELECT 42', $result);
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('A B', $result);
    }

    public function testGenerateAugmentedInsertRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        $result = $generator->generate('insert', 6);

        self::assertNotSame('', $result);
    }

    public function testGenerateAugmentedDeleteRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        $result = $generator->generate('delete', 6);

        self::assertNotSame('', $result);
    }

    public function testGenerateAugmentedUpdateRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        $result = $generator->generate('update', 6);

        self::assertNotSame('', $result);
    }

    public function testGenerateAugmentedDropTableRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        $result = $generator->generate('drop_table', 6);

        self::assertNotSame('', $result);
    }

    public function testGenerateAugmentedAlterTableRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        $result = $generator->generate('alter_table', 6);

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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Exceeded derivation limit while generating SQL.');

        $generator->generate('infinite');
    }

    #[DataProvider('providerExactDerivationLimitGrammar')]
    public function testGenerateAllowsDerivationAtExactLimitBoundary(Grammar $grammar): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('DONE', $generator->generate('n0'));
    }

    public function testGenerateThrowsOnEmptyAlternatives(): void
    {
        $grammar = new Grammar('empty', [
            'empty' => new ProductionRule('empty', []),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Production rule 'empty' has no alternatives.");

        $generator->generate('empty');
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SELECT unknown_rule', $result);
    }

    public function testGenerateContinuesAfterReplacingUnknownNonTerminalWithTerminal(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new NonTerminal('unknown_rule'),
                    new NonTerminal('known'),
                ]),
            ]),
            'known' => new ProductionRule('known', [
                new Production([new Terminal('OK')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('unknown_rule OK', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt');

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
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        $result = $generator->generate('stmt');

        self::assertMatchesRegularExpression($pattern, $result);
    }

    #[DataProvider('providerCanonicalIdentifierToken')]
    public function testGenerateCanonicalIdentifierToken(string $terminalName): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        self::assertSame('_i0', $generator->generate('stmt'));
    }

    public function testGenerateAllocatesFreshCanonicalIdentifiersAcrossIdentifierVariants(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('ID'),
                    new Terminal('id'),
                    new Terminal('idj'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        self::assertSame('_i0 _i1 _i2', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt');

        self::assertMatchesRegularExpression($pattern, $result);
    }

    #[DataProvider('providerGenerateExactCompoundKeyword')]
    public function testGenerateExactCompoundKeyword(string $terminalName, string $expected): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $faker = new class () extends \Faker\Generator {
            public function __construct()
            {
                parent::__construct();
            }

            /**
             * @param array<array-key, mixed> $elements
             */
            public function randomElement($elements = ['a', 'b', 'c']): mixed
            {
                if ($elements === []) {
                    throw new LogicException('randomElement requires a non-empty array.');
                }

                $resolved = array_values($elements);

                return $resolved[0];
            }
        };
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider(Factory::create()));

        self::assertSame($expected, $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('COUNT(*)', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('a.b', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('[x]', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('a, b', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('SELECT 1;', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('x)', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('(x', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('a->b', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('"func"()', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('+ (x)', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame('A B C', $generator->generate('stmt'));
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
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('A', $result);
        self::assertSame($result, trim($result));
    }

    public function testAugmentGrammarRemovesWithinGroupFromExpr(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);
        $exprRule = $augmented->ruleMap['expr'];

        $terminals = array_merge(...array_map(
            static fn (Production $alt): array => array_filter(
                $alt->symbols,
                static fn (Symbol $sym): bool => $sym instanceof Terminal,
            ),
            $exprRule->alternatives,
        ));
        array_walk($terminals, static function (Terminal $sym): void {
            self::assertNotSame('WITHIN', $sym->value, 'expr should not contain WITHIN terminal');
        });
    }

    public function testAugmentGrammarRemovesTriggerRaiseFromGeneralExpr(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);
        $exprRule = $augmented->ruleMap['expr'];

        self::assertSame([], array_values(array_filter(
            $exprRule->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;

                return $first instanceof Terminal && $first->value === 'RAISE';
            },
        )));
    }

    public function testAugmentGrammarPreservesNonWithinAndNonRaiseExprAlternatives(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('SELECT')]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new Terminal('WITHIN'), new NonTerminal('group_clause')]),
                new Production([new Terminal('RAISE'), new NonTerminal('term')]),
                new Production([new NonTerminal('term')]),
                new Production([new Terminal('ABS'), new NonTerminal('term')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame(
            [
                ['term'],
                ['ABS', 'term'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['expr']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarRemovesOrderByFromDelete(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);
        $deleteRule = $augmented->ruleMap['delete'];

        $nonTerminals = array_merge(...array_map(
            static fn (Production $alt): array => array_filter(
                $alt->symbols,
                static fn (Symbol $sym): bool => $sym instanceof NonTerminal,
            ),
            $deleteRule->alternatives,
        ));
        array_walk($nonTerminals, static function (NonTerminal $sym): void {
            self::assertNotSame('orderby_opt', $sym->value, 'delete should not contain orderby_opt');
        });
    }

    public function testAugmentGrammarRemovesEmptyWindowDefinitions(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        (static function () use ($augmented): void {
            if (!isset($augmented->ruleMap['window'])) {
                self::markTestSkipped('Grammar does not contain window rule.');
            }
        })();

        $windowRule = $augmented->ruleMap['window'];

        $altsWithoutTerminal = array_filter(
            $windowRule->alternatives,
            static fn (Production $alt): bool => count(array_filter(
                $alt->symbols,
                static fn (Symbol $sym): bool => $sym instanceof Terminal,
            )) === 0,
        );
        self::assertCount(0, $altsWithoutTerminal, 'window alternative should contain at least one terminal keyword');
    }

    public function testAugmentGrammarRetainsOnlyWindowAlternativesThatAreNotFrameOptOnly(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('SELECT')]),
            ]),
            'window' => new ProductionRule('window', [
                new Production([new NonTerminal('nm'), new NonTerminal('frame_opt')]),
                new Production([new Terminal('PARTITION'), new NonTerminal('nm'), new NonTerminal('frame_opt')]),
                new Production([new NonTerminal('other'), new NonTerminal('frame_opt')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame(
            [
                ['PARTITION', 'nm', 'frame_opt'],
                ['other', 'frame_opt'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['window']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarRemovesKeywordOnlyNmnumAlternatives(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['nmnum']->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;

                return $first instanceof Terminal && in_array($first->value, ['ON', 'DELETE', 'DEFAULT'], true);
            },
        )));
    }

    public function testAugmentGrammarLeavesNmnumRuleMapUntouchedWhenNmnumIsMissing(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('SELECT')]),
            ]),
            'helper' => new ProductionRule('helper', [
                new Production([new Terminal('VALUE')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertArrayHasKey('cmd', $generator->compiledGrammar()->ruleMap);
        self::assertArrayHasKey('helper', $generator->compiledGrammar()->ruleMap);
        self::assertArrayNotHasKey('nmnum', $generator->compiledGrammar()->ruleMap);
    }

    public function testAugmentGrammarPreservesNonTerminalNmnumAlternativesAndReindexesTheList(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('SELECT')]),
            ]),
            'nmnum' => new ProductionRule('nmnum', [
                new Production([new NonTerminal('ON')]),
                new Production([new Terminal('ON')]),
                new Production([new Terminal('VALUE')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $alternatives = $generator->compiledGrammar()->ruleMap['nmnum']->alternatives;

        self::assertSame([0, 1], array_keys($alternatives));
        self::assertSame(
            [
                ['nt:ON'],
                ['t:VALUE'],
            ],
            array_map(static fn (Production $alt): array => array_map(
                static fn (Symbol $symbol): string => $symbol instanceof NonTerminal
                    ? 'nt:' . $symbol->value()
                    : 't:' . $symbol->value(),
                $alt->symbols,
            ), $alternatives),
        );
    }

    public function testAugmentGrammarRestrictsNmToIdentifierTokens(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['nm']->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;

                return $first instanceof Terminal && $first->value === 'STRING';
            },
        )));
    }

    public function testAugmentGrammarLeavesNmRuleMapUntouchedWhenNmIsMissing(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('SELECT')]),
            ]),
            'helper' => new ProductionRule('helper', [
                new Production([new Terminal('VALUE')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertArrayHasKey('cmd', $generator->compiledGrammar()->ruleMap);
        self::assertArrayHasKey('helper', $generator->compiledGrammar()->ruleMap);
        self::assertArrayNotHasKey('nm', $generator->compiledGrammar()->ruleMap);
    }

    public function testAugmentGrammarReindexesFilteredNmAlternatives(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('SELECT')]),
            ]),
            'nm' => new ProductionRule('nm', [
                new Production([new Terminal('STRING')]),
                new Production([new Terminal('ID')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $alternatives = $generator->compiledGrammar()->ruleMap['nm']->alternatives;

        self::assertSame([0], array_keys($alternatives));
        self::assertSame([['t:ID']], array_map(
            static fn (Production $alt): array => array_map(
                static fn (Symbol $symbol): string => $symbol instanceof NonTerminal
                    ? 'nt:' . $symbol->value()
                    : 't:' . $symbol->value(),
                $alt->symbols,
            ),
            $alternatives,
        ));
    }

    public function testAugmentGrammarPromotesCreateTableToCompleteStatement(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('create_table_head', $augmented->ruleMap);
        self::assertArrayHasKey('safe_dbnm', $augmented->ruleMap);
        self::assertSame(
            [
                [],
                ['DOT', 'nm'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['safe_dbnm']->alternatives,
            ),
        );
        self::assertSame(
            [
                ['createkw', 'TABLE', 'ifnotexists', 'nm', 'safe_dbnm'],
                ['createkw', 'TEMP', 'TABLE', 'ifnotexists', 'nm'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['create_table_head']->alternatives,
            ),
        );
        self::assertSame(['create_table_head', 'create_table_args'], array_map(
            static function (Symbol $symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['create_table']->alternatives[0]->symbols,
        ));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['cmd']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function (Symbol $symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['create_table', 'create_table_args'];
            },
        )));
    }

    public function testAugmentGrammarLeavesCreateTableRulesUntouchedWhenRequiredRulesAreMissing(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new NonTerminal('create_table'), new NonTerminal('create_table_args')]),
            ]),
            'create_table' => new ProductionRule('create_table', [
                new Production([new Terminal('CREATE')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertArrayNotHasKey('safe_dbnm', $generator->compiledGrammar()->ruleMap);
        self::assertArrayHasKey('cmd', $generator->compiledGrammar()->ruleMap);
        self::assertArrayHasKey('create_table', $generator->compiledGrammar()->ruleMap);
        self::assertArrayNotHasKey('create_table_head', $generator->compiledGrammar()->ruleMap);
    }

    public function testAugmentGrammarRewritesCreateTableCommandAlternativeToWrapperRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertContains(
            ['create_table'],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['cmd']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarExtractsInsertRuleFromTwoSymbolCommandAlternatives(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([
                    new NonTerminal('prefix'),
                    new NonTerminal('insert_cmd'),
                ]),
            ]),
            'prefix' => new ProductionRule('prefix', [
                new Production([]),
            ]),
            'insert_cmd' => new ProductionRule('insert_cmd', [
                new Production([new Terminal('INSERT')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertArrayHasKey('insert', $generator->compiledGrammar()->ruleMap);
        self::assertSame(
            [['prefix', 'insert_cmd']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['insert']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarConstrainsAttachAndDetachExpressions(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('attach_stmt', $augmented->ruleMap);
        self::assertArrayHasKey('detach_stmt', $augmented->ruleMap);
        self::assertArrayHasKey('safe_attach_filename_expr', $augmented->ruleMap);
        self::assertArrayHasKey('safe_attach_schema_expr', $augmented->ruleMap);
        self::assertSame(
            [['STRING']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['safe_attach_filename_expr']->alternatives,
            ),
        );
        self::assertSame(
            [['nm']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['safe_attach_schema_expr']->alternatives,
            ),
        );
        self::assertSame(
            [['ATTACH', 'database_kw_opt', 'safe_attach_filename_expr', 'AS', 'safe_attach_schema_expr', 'key_opt']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['attach_stmt']->alternatives,
            ),
        );
        self::assertSame(
            [['DETACH', 'database_kw_opt', 'safe_attach_schema_expr']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['detach_stmt']->alternatives,
            ),
        );
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['cmd']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function (Symbol $symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['ATTACH', 'database_kw_opt', 'expr', 'AS', 'expr', 'key_opt']
                    || $names === ['DETACH', 'database_kw_opt', 'expr'];
            },
        )));
    }

    public function testAugmentGrammarRewritesAttachAndDetachCommandAlternativesToWrapperRules(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertContains(
            ['attach_stmt'],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['cmd']->alternatives,
            ),
        );
        self::assertContains(
            ['detach_stmt'],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['cmd']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarConstrainsVacuumIntoExpressions(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('vacuum_stmt', $augmented->ruleMap);
        self::assertArrayHasKey('safe_vinto', $augmented->ruleMap);
        self::assertArrayHasKey('safe_vacuum_into_expr', $augmented->ruleMap);
        self::assertSame(
            [['STRING']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['safe_vacuum_into_expr']->alternatives,
            ),
        );
        self::assertSame(
            [
                [],
                ['INTO', 'safe_vacuum_into_expr'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['safe_vinto']->alternatives,
            ),
        );
        self::assertSame(
            [
                ['VACUUM', 'safe_vinto'],
                ['VACUUM', 'nm', 'safe_vinto'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['vacuum_stmt']->alternatives,
            ),
        );
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['cmd']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function (Symbol $symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['VACUUM', 'vinto']
                    || $names === ['VACUUM', 'nm', 'vinto'];
            },
        )));
    }

    public function testAugmentGrammarRewritesVacuumCommandAlternativesToWrapperRule(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertContains(
            ['vacuum_stmt'],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['cmd']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarLeavesTemporaryObjectRulesUntouchedWhenRequiredRulesAreMissing(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('CREATE'), new Terminal('VIEW')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertArrayNotHasKey('create_view_stmt', $generator->compiledGrammar()->ruleMap);
        self::assertArrayNotHasKey('create_trigger_stmt', $generator->compiledGrammar()->ruleMap);
    }

    public function testAugmentGrammarBindsTemporaryObjectNamesToUnqualifiedForms(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('create_view_stmt', $augmented->ruleMap);
        self::assertArrayHasKey('create_trigger_stmt', $augmented->ruleMap);
        self::assertSame(
            [
                ['createkw', 'VIEW', 'ifnotexists', 'nm', 'safe_dbnm', 'eidlist_opt', 'AS', 'select'],
                ['createkw', 'TEMP', 'VIEW', 'ifnotexists', 'nm', 'eidlist_opt', 'AS', 'select'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['create_view_stmt']->alternatives,
            ),
        );
        self::assertSame(
            [
                ['TRIGGER', 'ifnotexists', 'nm', 'safe_dbnm', 'trigger_time', 'trigger_event', 'ON', 'fullname', 'foreach_clause', 'when_clause'],
                ['TEMP', 'TRIGGER', 'ifnotexists', 'nm', 'trigger_time', 'trigger_event', 'ON', 'fullname', 'foreach_clause', 'when_clause'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['trigger_decl']->alternatives,
            ),
        );
        self::assertSame(
            [['createkw', 'trigger_decl', 'BEGIN', 'trigger_cmd_list', 'END']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['create_trigger_stmt']->alternatives,
            ),
        );
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['trigger_decl']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function (Symbol $symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['TEMP', 'TRIGGER', 'ifnotexists', 'nm', 'trigger_time', 'trigger_event', 'ON', 'fullname', 'foreach_clause', 'when_clause'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['trigger_decl']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function (Symbol $symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['TEMP', 'TRIGGER', 'ifnotexists', 'nm', 'safe_dbnm', 'trigger_time', 'trigger_event', 'ON', 'fullname', 'foreach_clause', 'when_clause'];
            },
        )));
    }

    public function testAugmentGrammarRewritesCmdAlternativesToTemporaryViewAndTriggerWrappers(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertContains(
            ['create_view_stmt'],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['cmd']->alternatives,
            ),
        );
        self::assertContains(
            ['create_trigger_stmt'],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['cmd']->alternatives,
            ),
        );
        self::assertNotContains(
            ['createkw', 'temp', 'VIEW', 'ifnotexists', 'nm', 'dbnm', 'eidlist_opt', 'AS', 'select'],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['cmd']->alternatives,
            ),
        );
        self::assertNotContains(
            ['createkw', 'trigger_decl', 'BEGIN', 'trigger_cmd_list', 'END'],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['cmd']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarBindsStarResultColumnsToFromClauses(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_selcollist_no_from', $augmented->ruleMap);
        self::assertArrayHasKey('safe_from_clause', $augmented->ruleMap);
        self::assertSame(
            [['FROM', 'seltablist']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['safe_from_clause']->alternatives,
            ),
        );
        self::assertSame(
            [['expr']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['safe_select_result_expr']->alternatives,
            ),
        );
        self::assertSame(
            [['term']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['safe_select_value_expr']->alternatives,
            ),
        );
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['safe_selcollist_no_from']->alternatives,
            static function (Production $alt): bool {
                foreach ($alt->symbols as $symbol) {
                    if ($symbol instanceof Terminal && $symbol->value === 'STAR') {
                        return true;
                    }
                }

                return false;
            },
        )));
        self::assertSame(
            [
                ['sclp', 'scanpt', 'expr', 'scanpt', 'as'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['safe_selcollist_no_from']->alternatives,
            ),
        );
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['oneselect']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function (Symbol $symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['SELECT', 'distinct', 'safe_selcollist_no_from', 'where_opt', 'groupby_opt', 'having_opt', 'window_clause', 'orderby_opt', 'limit_opt']
                    || $names === ['SELECT', 'distinct', 'selcollist', 'safe_from_clause', 'where_opt', 'groupby_opt', 'having_opt', 'orderby_opt', 'limit_opt'];
            },
        )));
    }

    public function testAugmentGrammarRetainsNonStarSelectResultColumnsForFromFreeSelects(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('SELECT')]),
            ]),
            'selectnowith' => new ProductionRule('selectnowith', [
                new Production([new NonTerminal('oneselect')]),
            ]),
            'oneselect' => new ProductionRule('oneselect', [
                new Production([new Terminal('SELECT'), new NonTerminal('selcollist')]),
            ]),
            'selcollist' => new ProductionRule('selcollist', [
                new Production([new NonTerminal('expr'), new Terminal('AS'), new NonTerminal('alias')]),
                new Production([new Terminal('STAR')]),
            ]),
            'multiselect_op' => new ProductionRule('multiselect_op', [
                new Production([new Terminal('UNION')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame(
            [
                ['expr', 'AS', 'alias'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['safe_selcollist_no_from']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarReindexesSafeNoFromAlternativesAfterFilteringStarColumns(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('SELECT')]),
            ]),
            'selectnowith' => new ProductionRule('selectnowith', [
                new Production([new NonTerminal('oneselect')]),
            ]),
            'oneselect' => new ProductionRule('oneselect', [
                new Production([new Terminal('SELECT'), new NonTerminal('selcollist')]),
            ]),
            'selcollist' => new ProductionRule('selcollist', [
                new Production([new NonTerminal('expr')]),
                new Production([new Terminal('STAR')]),
                new Production([new NonTerminal('alias')]),
            ]),
            'multiselect_op' => new ProductionRule('multiselect_op', [
                new Production([new Terminal('UNION')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame([0, 1], array_keys($generator->compiledGrammar()->ruleMap['safe_selcollist_no_from']->alternatives));
    }

    public function testAugmentGrammarExtractsDeleteAndUpdateRulesFromTerminalCommandAlternatives(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('DELETE'), new NonTerminal('from')]),
                new Production([new Terminal('UPDATE'), new NonTerminal('qualified_table_name'), new Terminal('SET')]),
                new Production([new Terminal('ALTER'), new Terminal('TABLE'), new NonTerminal('nm')]),
                new Production([new Terminal('DROP'), new Terminal('TABLE'), new NonTerminal('nm')]),
            ]),
        ]);
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame(
            [['DELETE', 'from']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['delete']->alternatives,
            ),
        );
        self::assertSame(
            [['UPDATE', 'qualified_table_name', 'SET']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['update']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarIntroducesFiniteSetOperationFamilies(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('setop_select_stmt', $augmented->ruleMap);
        self::assertArrayHasKey('setop_select_stmt_1', $augmented->ruleMap);
        self::assertArrayHasKey('setop_select_stmt_8', $augmented->ruleMap);
        self::assertSame(
            [
                ['setop_select_operand_1', 'multiselect_op', 'setop_select_operand_1'],
                ['setop_select_stmt_1', 'multiselect_op', 'setop_select_operand_1'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['setop_select_stmt_1']->alternatives,
            ),
        );
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['selectnowith']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function (Symbol $symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['selectnowith', 'multiselect_op', 'oneselect'];
            },
        )));
    }

    public function testAugmentGrammarKeepsDirectAndSetOperationSelectNowithAlternatives(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame(
            [
                ['oneselect'],
                ['setop_select_stmt'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['selectnowith']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarAccumulatesFiniteSetOperationFamilies(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame(
            [
                ['setop_select_stmt_1'],
                ['setop_select_stmt_2'],
                ['setop_select_stmt_3'],
                ['setop_select_stmt_4'],
                ['setop_select_stmt_5'],
                ['setop_select_stmt_6'],
                ['setop_select_stmt_7'],
                ['setop_select_stmt_8'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['setop_select_stmt']->alternatives,
            ),
        );
        self::assertSame(
            [
                ['SELECT', 'distinct', 'select_result_list_1', 'from', 'where_opt', 'groupby_opt', 'having_opt', 'orderby_opt', 'limit_opt'],
                ['SELECT', 'distinct', 'select_result_list_1', 'from', 'where_opt', 'groupby_opt', 'having_opt', 'window_clause', 'orderby_opt', 'limit_opt'],
                ['select_values_clause_1'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['setop_select_operand_1']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarKeepsCompleteSelectWithFromAlternatives(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame(
            [
                ['SELECT', 'distinct', 'safe_selcollist_no_from', 'where_opt', 'groupby_opt', 'having_opt', 'orderby_opt', 'limit_opt'],
                ['SELECT', 'distinct', 'safe_selcollist_no_from', 'where_opt', 'groupby_opt', 'having_opt', 'window_clause', 'orderby_opt', 'limit_opt'],
                ['SELECT', 'distinct', 'selcollist', 'safe_from_clause', 'where_opt', 'groupby_opt', 'having_opt', 'orderby_opt', 'limit_opt'],
                ['SELECT', 'distinct', 'selcollist', 'safe_from_clause', 'where_opt', 'groupby_opt', 'having_opt', 'window_clause', 'orderby_opt', 'limit_opt'],
                ['select_values_clause'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['oneselect']->alternatives,
            ),
        );
    }

    public function testAugmentGrammarIntroducesFiniteValuesClauseFamilies(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('select_values_clause', $augmented->ruleMap);
        self::assertArrayHasKey('select_values_clause_1', $augmented->ruleMap);
        self::assertArrayHasKey('select_values_clause_8', $augmented->ruleMap);
        self::assertSame(
            [
                ['select_values_clause_1'],
                ['select_values_clause_2'],
                ['select_values_clause_3'],
                ['select_values_clause_4'],
                ['select_values_clause_5'],
                ['select_values_clause_6'],
                ['select_values_clause_7'],
                ['select_values_clause_8'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['select_values_clause']->alternatives,
            ),
        );
        self::assertSame(
            [['VALUES', 'select_value_row_list_1']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['select_values_clause_1']->alternatives,
            ),
        );
        self::assertSame(
            [['LP', 'select_value_expr_list_1', 'RP']],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['select_value_row_1']->alternatives,
            ),
        );
        self::assertSame(
            [
                ['select_value_row_1'],
                ['select_value_row_list_1', 'COMMA', 'select_value_row_1'],
            ],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $augmented->ruleMap['select_value_row_list_1']->alternatives,
            ),
        );
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['oneselect']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function (Symbol $symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['values'] || $names === ['mvalues'];
            },
        )));
    }

    public function testAugmentGrammarBuildsExactCommaSeparatedValueExpressionLists(): void
    {
        $grammar = SqliteGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new SqliteProvider($faker));

        self::assertSame(
            [[
                'safe_select_value_expr',
                'COMMA',
                'safe_select_value_expr',
                'COMMA',
                'safe_select_value_expr',
            ]],
            array_map(
                static fn (Production $alt): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                ),
                $generator->compiledGrammar()->ruleMap['select_value_expr_list_3']->alternatives,
            ),
        );
    }

    public function testGenerateIdentifierQuotesReservedWords(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('ID')]),
            ]),
        ]);

        $reservedWords = ['as', 'by', 'do', 'if', 'in', 'is', 'no', 'of', 'on', 'or', 'to',
            'add', 'all', 'and', 'for', 'key', 'not', 'set'];

        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        array_map(static function (int $seed) use ($faker, $generator, $reservedWords): void {
            $faker->seed($seed);
            $result = $generator->generate('stmt');

            $bare = strtolower(trim($result, '"'));
            if (in_array($bare, $reservedWords, true)) {
                self::assertStringStartsWith('"', $result, "Seed $seed: reserved word '$result' should be quoted");
                self::assertStringEndsWith('"', $result, "Seed $seed: reserved word '$result' should be quoted");
            }
        }, range(0, 9999));

        self::addToAssertionCount(1);
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
     * @return iterable<string, array{Grammar}>
     */
    public static function providerExactDerivationLimitGrammar(): iterable
    {
        $ruleMap = [];
        foreach (range(0, 4999) as $index) {
            $ruleMap["n{$index}"] = new ProductionRule("n{$index}", [
                new Production([
                    $index === 4999 ? new Terminal('DONE') : new NonTerminal('n' . ($index + 1)),
                ]),
            ]);
        }

        yield 'exact boundary chain' => [new Grammar('n0', $ruleMap)];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function providerContractGrammarEvidence(): iterable
    {
        foreach (GrammarClaimLoader::loadGrammarEvidence(__DIR__ . '/../../../spec/claims/sqlite.contract.json') as $index => $case) {
            yield sprintf('%s #%d', $case['claim_id'], $index) => [$case['evidence'], $case['claim_id']];
        }
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
        yield 'ID' => ['ID', '/^[a-z_][a-z0-9_]*$/'];
        yield 'id' => ['id', '/^[a-z_][a-z0-9_]*$/'];
        yield 'idj' => ['idj', '/^[a-z_][a-z0-9_]*$/'];
        yield 'ids' => ['ids', '/^"[a-zA-Z0-9_]+"$/'];
        yield 'STRING' => ['STRING', "/^'[a-zA-Z0-9_]+'$/"];
        yield 'INTEGER' => ['INTEGER', '/^[1-9]\d*$/'];
        yield 'number' => ['number', '/^[1-9]\d*$/'];
        yield 'QNUMBER' => ['QNUMBER', '/^[1-9]\d*$/'];
        yield 'VARIABLE' => ['VARIABLE', '/^\?\d{1,2}$/'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCanonicalIdentifierToken(): iterable
    {
        yield 'ID' => ['ID'];
        yield 'id' => ['id'];
        yield 'idj' => ['idj'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateCompoundKeyword(): iterable
    {
        yield 'JOIN_KW' => ['JOIN_KW', '/^(LEFT|RIGHT|INNER|CROSS|NATURAL LEFT|NATURAL INNER|NATURAL CROSS)$/'];
        yield 'CTIME_KW' => ['CTIME_KW', '/^(CURRENT_TIME|CURRENT_DATE|CURRENT_TIMESTAMP)$/'];
        yield 'LIKE_KW' => ['LIKE_KW', '/^(LIKE|GLOB)$/'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateExactCompoundKeyword(): iterable
    {
        yield 'join left' => ['JOIN_KW', 'LEFT'];
        yield 'ctime current_time' => ['CTIME_KW', 'CURRENT_TIME'];
        yield 'like like' => ['LIKE_KW', 'LIKE'];
    }
}
