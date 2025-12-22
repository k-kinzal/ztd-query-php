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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new MySqlProvider($faker));

        $result = $generator->generate('stmt');

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
