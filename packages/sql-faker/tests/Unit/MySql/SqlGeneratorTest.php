<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use Faker\Factory;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;
use SqlFaker\MySql\SqlGenerator;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\MySqlProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SqlGenerator::class)]
#[CoversClass(TokenJoiner::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(MySqlProvider::class)]
#[CoversClass(TerminationAnalyzer::class)]
#[Medium]
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SELECT foo', $result);
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate();

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result1 = $generator->generate('stmt');
        $result2 = $generator->generate('stmt');

        self::assertSame('a', $result1);
        self::assertSame('a', $result2);
    }

    public function testGenerateWithRealGrammar(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $faker->seed(42);
        $provider = new MySqlProvider($faker);

        $generator = new SqlGenerator($grammar, $faker, $provider);
        $result = $generator->generate('literal', 1);

        self::assertNotSame('', $result);
    }

    #[DataProvider('providerCanonicalIdentifierRule')]
    public function testAugmentGrammarKeepsCanonicalIdentifierAlternatives(string $ruleName): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        $rule = $augmented->ruleMap[$ruleName];
        self::assertCount(1, $rule->alternatives, "{$ruleName} should be reduced to its canonical identifier form");
        $symbol = $rule->alternatives[0]->symbols[0] ?? null;
        self::assertInstanceOf(NonTerminal::class, $symbol);
        self::assertSame('IDENT_sys', $symbol->value);
    }

    public function testAugmentGrammarCanonicalizesUserRule(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertCount(1, $augmented->ruleMap['user']->alternatives);
        self::assertSame(['TEXT_STRING_sys', '@', 'TEXT_STRING_sys'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['user']->alternatives[0]->symbols,
        ));
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $resultZero = $generator->generate('stmt', 0);
        $resultNegative = $generator->generate('stmt', -10);
        $resultOne = $generator->generate('stmt', 1);

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt', 1);

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt', 1);

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
        $generator1 = new SqlGenerator($grammar, $faker1, $provider1);
        $result1 = $generator1->generate('stmt', PHP_INT_MAX);

        $faker2 = Factory::create();
        $provider2 = new MySqlProvider($faker2);
        $faker2->seed($seed2);
        $generator2 = new SqlGenerator($grammar, $faker2, $provider2);
        $result2 = $generator2->generate('stmt', PHP_INT_MAX);

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt', 3);

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('1ST 2ND', $result);
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SELECT 42', $result);
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Exceeded derivation limit while generating SQL.');

        $generator->generate('infinite');
    }

    public function testGenerateThrowsOnEmptyAlternatives(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('empty', [
            'empty' => new ProductionRule('empty', []),
        ]);
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Production rule has no alternatives.');

        $generator->generate('empty');
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $this->expectException(\LogicException::class);

        $generator->generate('non_existent_rule');
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame($expected, $result);
    }

    #[DataProvider('providerGenerateLexicalToken')]
    public function testGenerateLexicalToken(string $terminalName, string $pattern): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('`incomplete ()', $result);
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('@var', $result);
    }

    public function testAugmentGrammarExcludesNullSafeEqualsFromAllOrAnyComparisons(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('comp_op_all_or_any', $augmented->ruleMap);
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['comp_op_all_or_any']->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;

                return $first instanceof Terminal && $first->value === 'EQUAL_SYM';
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['bool_pri']->alternatives,
            static function (Production $alt): bool {
                return count($alt->symbols) === 4
                    && $alt->symbols[0] instanceof NonTerminal
                    && $alt->symbols[0]->value === 'bool_pri'
                    && $alt->symbols[1] instanceof NonTerminal
                    && $alt->symbols[1]->value === 'comp_op_all_or_any'
                    && $alt->symbols[2] instanceof NonTerminal
                    && $alt->symbols[2]->value === 'all_or_any'
                    && $alt->symbols[3] instanceof NonTerminal
                    && $alt->symbols[3]->value === 'table_subquery';
            },
        )));
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('a <=>', $result);
    }

    public function testAugmentGrammarExcludesCommitAndChainReleaseCombination(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['commit']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return array_slice($names, -3) === ['AND_SYM', 'CHAIN_SYM', 'RELEASE_SYM'];
            },
        )));
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('COMMIT AND CHAIN NO RELEASE', $result);
    }

    public function testAugmentGrammarRemovesCloneEndpointVariant(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertCount(1, $augmented->ruleMap['clone_stmt']->alternatives);
        self::assertSame([
            'CLONE_SYM',
            'LOCAL_SYM',
            'DATA_SYM',
            'DIRECTORY_SYM',
            'opt_equal',
            'TEXT_STRING_filesystem',
        ], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['clone_stmt']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarCanonicalizesFlushOptions(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['opt_no_write_to_binlog']->alternatives,
            static function (Production $alt): bool {
                return in_array('LOCAL_SYM', array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                ), true);
            },
        )));
        self::assertCount(1, $augmented->ruleMap['flush_options_list']->alternatives);
        self::assertSame(['safe_flush_option'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['flush_options_list']->alternatives[0]->symbols,
        ));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['safe_flush_option']->alternatives,
            static function (Production $alt): bool {
                return in_array('RESOURCES', array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                ), true);
            },
        )));
    }

    public function testAugmentGrammarSeparatesRoleRevokeFromPrivilegeRevoke(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['revoke']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['REVOKE', 'if_exists', 'role_or_privilege_list', 'FROM', 'user_list', 'opt_ignore_unknown_user']
                    || $names === ['REVOKE', 'if_exists', 'role_or_privilege_list', 'ON_SYM', 'opt_acl_type', 'grant_ident', 'FROM', 'user_list', 'opt_ignore_unknown_user'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['revoke']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['REVOKE', 'if_exists', 'revoked_role_list', 'FROM', 'user_list', 'opt_ignore_unknown_user'];
            },
        )));
    }

    public function testAugmentGrammarNormalizesRollbackCombinations(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['rollback']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['ROLLBACK_SYM', 'opt_work', 'AND_SYM', 'CHAIN_SYM', 'RELEASE_SYM'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['rollback']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['ROLLBACK_SYM', 'opt_work', 'AND_SYM', 'CHAIN_SYM', 'NO_SYM', 'RELEASE_SYM'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsLimitOptionsToFiniteSafeSubset(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_limit_literal', $augmented->ruleMap);
        self::assertSame(['safe_limit_literal'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['limit_option']->alternatives[0]->symbols,
        ));
        self::assertSame(['0', '1', '2', '10', '100'], array_map(
            static fn (Production $alt): string => $alt->symbols[0] instanceof Terminal ? $alt->symbols[0]->value : '',
            $augmented->ruleMap['safe_limit_literal']->alternatives,
        ));
    }

    public function testAugmentGrammarRestrictsChangeReplicationSourceOptionsToSafeScalarSubset(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('change_replication_source_stmt', $augmented->ruleMap);
        self::assertSame(['source_def'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['source_defs']->alternatives[0]->symbols,
        ));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['source_def']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['change_replication_source_compression_algorithm', 'EQ', 'TEXT_STRING_sys']
                    || $names === ['source_file_def']
                    || $names === ['PRIVILEGE_CHECKS_USER_SYM', 'EQ', 'privilege_check_def']
                    || $names === ['ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS_SYM', 'EQ', 'assign_gtids_to_anonymous_transactions_def'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsSrsIdentifiersToCanonicalNumericTokens(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('srs_numeric_id', $augmented->ruleMap);
        self::assertNotSame([], $augmented->ruleMap['srs_numeric_id']->alternatives);
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['srs_numeric_id']->alternatives,
            static function (Production $alt): bool {
                return count($alt->symbols) !== 1 || !$alt->symbols[0] instanceof Terminal;
            },
        )));
        self::assertSame(['1', '999999'], array_map(
            static fn (Production $alt): string => $alt->symbols[0] instanceof Terminal ? $alt->symbols[0]->value : '',
            $augmented->ruleMap['srs_numeric_id']->alternatives,
        ));

        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['create_srs_stmt']->alternatives,
            static function (Production $alt): bool {
                return in_array('srs_numeric_id', array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                ), true);
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['drop_srs_stmt']->alternatives,
            static function (Production $alt): bool {
                return in_array('srs_numeric_id', array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                ), true);
            },
        )));
    }

    public function testAugmentGrammarRequiresAlterEventOptionClause(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertCount(5, $augmented->ruleMap['alter_event_stmt']->alternatives);
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['alter_event_stmt']->alternatives,
            static function (Production $alt): bool {
                return count(array_filter(
                    $alt->symbols,
                    static fn ($symbol): bool => $symbol instanceof NonTerminal
                        && str_starts_with($symbol->value, 'nonempty_'),
                )) !== 1;
            },
        )));
    }

    public function testAugmentGrammarFactorsAlterDatabaseEncryptionFamilyIntoDedicatedRoot(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('alter_database_encryption_stmt', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['alter_database_stmt']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['alter_database_encryption_stmt'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['alter_database_encryption_stmt']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );

                return $names === ['ALTER', 'DATABASE', 'ident', 'alter_database_options_with_encryption'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsTopLevelTableValueConstructorsToNonEmptyRows(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('table_value_constructor_1', $augmented->ruleMap);
        self::assertArrayHasKey('table_value_constructor_8', $augmented->ruleMap);
        self::assertSame(['table_value_constructor_1'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['table_value_constructor']->alternatives[0]->symbols,
        ));
        self::assertSame(['VALUES', 'table_value_values_row_list_1'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['table_value_constructor_1']->alternatives[0]->symbols,
        ));
        self::assertSame(['ROW_SYM', '(', 'table_value_values_8', ')'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['table_value_row_value_explicit_8']->alternatives[0]->symbols,
        ));
        self::assertSame(['signed_literal'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['table_value_expr']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarConstrainsSignalSqlstateToCanonicalLiterals(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('signal_sqlstate_stmt', $augmented->ruleMap);
        self::assertSame(['signal_sqlstate_stmt'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['signal_stmt']->alternatives[0]->symbols,
        ));
        self::assertSame(['SQLSTATE_SYM', 'opt_value', 'safe_sqlstate_literal'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['sqlstate']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarRequiresNameAndDefinitionForCreateSrs(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_srs_definition_literal', $augmented->ruleMap);
        self::assertSame(['srs_name_attribute', 'srs_definition_attribute'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['srs_attributes']->alternatives[0]->symbols,
        ));
        self::assertSame(['DEFINITION_SYM', 'safe_srs_definition_literal'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['srs_definition_attribute']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarConstrainsSetSystemVariableFamily(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('set_system_variable_stmt', $augmented->ruleMap);
        self::assertSame(['set_system_variable_stmt'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['set']->alternatives[0]->symbols,
        ));
        self::assertSame(['autocommit', 'equal', 'boolean_numeric_option'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['safe_set_system_assignment']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarConstrainsReplicaUntilToAcceptedPairs(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame(['source_log_file', 'EQ', 'TEXT_STRING_sys_nonewline', ',', 'source_log_pos', 'EQ', 'ulonglong_num'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['replica_until']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarConstrainsDiagnosticsTargetsToUserVariables(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame(['@', 'ident_or_text'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['simple_target_specification']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarConstrainsExplainFormatAndAlterUserFactorDomains(): void
    {
        $grammar = Grammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $ref = new \ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame(['FORMAT_SYM', 'EQ', 'safe_explain_format_name'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['opt_explain_format']->alternatives[1]->symbols,
        ));
        self::assertSame(['2', 'FACTOR_SYM'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['factor']->alternatives[0]->symbols,
        ));
        self::assertSame(['alter_user_add_two_factors'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['alter_user']->alternatives[11]->symbols,
        ));
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertStringContainsString('@', $result);
        self::assertStringNotContainsString('.', explode('@', $result)[0]);
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('CURRENT_USER()', $result);
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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertMatchesRegularExpression('/^SELECT \d+\.\d+e-?\d+$/', $result);
    }

    public function testGenerateUsesFreshCanonicalIdentifiersWithinSingleStatement(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT_SYM'),
                    new Terminal('IDENT'),
                    new Terminal(','),
                    new Terminal('IDENT'),
                    new Terminal(','),
                    new Terminal('IDENT_QUOTED'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SELECT _i0, _i1, `_i2`', $result);
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
     * @return iterable<string, array{string}>
     */
    public static function providerCanonicalIdentifierRule(): iterable
    {
        yield 'ident' => ['ident'];
        yield 'label_ident' => ['label_ident'];
        yield 'role_ident' => ['role_ident'];
        yield 'lvalue_ident' => ['lvalue_ident'];
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
        yield 'IDENT_QUOTED' => ['IDENT_QUOTED', '/^`[a-z_][a-z0-9_]*`$/'];
        yield 'TEXT_STRING' => ['TEXT_STRING', "/^'[a-zA-Z0-9_]+'$/"];
        yield 'NCHAR_STRING' => ['NCHAR_STRING', "/^N'[a-zA-Z0-9_]+'$/"];
        yield 'DOLLAR_QUOTED_STRING_SYM' => ['DOLLAR_QUOTED_STRING_SYM', '/^\$\$[a-zA-Z0-9_]+\$\$$/'];
        yield 'NUM' => ['NUM', '/^\d+$/'];
        yield 'LONG_NUM' => ['LONG_NUM', '/^\d+$/'];
        yield 'ULONGLONG_NUM' => ['ULONGLONG_NUM', '/^\d+$/'];
        yield 'DECIMAL_NUM' => ['DECIMAL_NUM', '/^\d+\.\d+$/'];
        yield 'FLOAT_NUM' => ['FLOAT_NUM', '/^\d+\.\d+e-?\d+$/'];
        yield 'HEX_NUM' => ['HEX_NUM', '/^0x[0-9a-f]+$/'];
        yield 'BIN_NUM' => ['BIN_NUM', '/^0b[01]+$/'];
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
}
