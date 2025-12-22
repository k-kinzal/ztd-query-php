<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use Faker\Factory;
use Faker\Generator as FakerGenerator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\SqlGenerator;
use SqlFaker\MySqlProvider;

final class SqlGeneratorTest extends TestCase
{
    private FakerGenerator $faker;

    protected function setUp(): void
    {
        $this->faker = Factory::create();
        $this->faker->seed(12345);
    }

    // =========================================================================
    // generate() - Basic behavior
    // =========================================================================

    public function testGenerate(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT_SYM'),
                    new Terminal('foo'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('SELECT foo', $result);
    }

    public function testGenerateUsesSimpleStatementOrBeginAsDefaultStartRule(): void
    {
        $grammar = new Grammar('simple_statement_or_begin', [
            'simple_statement_or_begin' => new ProductionRule('simple_statement_or_begin', [
                new Production([new Terminal('DEFAULT_RULE_USED')]),
            ]),
            'other_rule' => new ProductionRule('other_rule', [
                new Production([new Terminal('OTHER_RULE_USED')]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

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
        $generator = new SqlGenerator($grammar, $this->faker);

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
        new MySqlProvider($faker);

        $generator = new SqlGenerator($grammar, $faker);
        $result = $generator->generate('literal', 1);

        self::assertNotEmpty($result);
    }

    // =========================================================================
    // generate() - targetDepth parameter
    // =========================================================================

    public function testGenerateTreatsTargetDepthLessThanOneAsOne(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                // Longer alternative (4 terminals)
                new Production([
                    new Terminal('A'),
                    new Terminal('B'),
                    new Terminal('C'),
                    new Terminal('D'),
                ]),
                // Shorter alternative (1 terminal)
                new Production([new Terminal('SHORT')]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        // targetDepth=0 should become 1, meaning shortest is selected at step 1
        $resultZero = $generator->generate('stmt', 0);
        $resultNegative = $generator->generate('stmt', -10);
        $resultOne = $generator->generate('stmt', 1);

        // All should pick the shorter alternative
        self::assertSame('SHORT', $resultZero);
        self::assertSame('SHORT', $resultNegative);
        self::assertSame('SHORT', $resultOne);
    }

    public function testGenerateSelectsShortestAlternativeAtTargetDepth(): void
    {
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
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt', 1);

        self::assertSame('SHORT', $result);
    }

    public function testGenerateSelectsFirstAlternativeOnLengthTie(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                // Both alternatives have exactly 1 terminal
                new Production([new Terminal('FIRST')]),
                new Production([new Terminal('SECOND')]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        // At targetDepth, when lengths are equal, first alternative wins
        $result = $generator->generate('stmt', 1);

        self::assertSame('FIRST', $result);
    }

    public function testGenerateSelectsRandomAlternativeBeforeTargetDepth(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('A')]),
                new Production([new Terminal('B')]),
                new Production([new Terminal('C')]),
            ]),
        ]);

        $results = [];
        for ($seed = 0; $seed < 100; $seed++) {
            $faker = Factory::create();
            $faker->seed($seed);
            $generator = new SqlGenerator($grammar, $faker);
            // PHP_INT_MAX means we never reach targetDepth, so random selection
            $results[$generator->generate('stmt', PHP_INT_MAX)] = true;
        }

        // With random selection, we should see multiple different results
        self::assertGreaterThan(1, count($results));
    }

    public function testGenerateSwitchesToShortestSelectionAtExactlyTargetDepth(): void
    {
        // Grammar: stmt -> inner -> choice (LONG or SHORT)
        // Step 1: expand stmt -> inner
        // Step 2: expand inner -> choice
        // At step 2, if targetDepth=2, we should use shortest selection
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('inner')]),
            ]),
            'inner' => new ProductionRule('inner', [
                new Production([new NonTerminal('choice')]),
            ]),
            'choice' => new ProductionRule('choice', [
                // Longer alternative
                new Production([new Terminal('L'), new Terminal('O'), new Terminal('N'), new Terminal('G')]),
                // Shorter alternative
                new Production([new Terminal('SHORT')]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        // At targetDepth=3, step 3 (expanding 'choice') uses shortest selection (>=)
        $result = $generator->generate('stmt', 3);

        self::assertSame('SHORT', $result);
    }

    // =========================================================================
    // generate() - Derivation behavior
    // =========================================================================

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
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        // Leftmost NonTerminal 'first' is expanded before 'second'
        self::assertSame('1ST 2ND', $result);
    }

    public function testGenerateWithNestedNonTerminals(): void
    {
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
        $generator = new SqlGenerator($grammar, $this->faker);

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
                // Empty production (epsilon)
                new Production([]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('A B', $result);
    }

    // =========================================================================
    // generate() - Exception cases
    // =========================================================================

    public function testGenerateThrowsAfterExceeding5000DerivationSteps(): void
    {
        // Verify the constant value is 5000
        $reflection = new ReflectionClass(SqlGenerator::class);
        $constant = $reflection->getConstant('DERIVATION_LIMIT');
        self::assertSame(5000, $constant);

        // The condition is `> 5000`, meaning:
        // - 5000 steps: OK
        // - 5001 steps: throws
        $grammar = new Grammar('infinite', [
            'infinite' => new ProductionRule('infinite', [
                new Production([
                    new NonTerminal('infinite'),
                    new Terminal('a'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Exceeded derivation limit while generating SQL.');

        $generator->generate('infinite');
    }

    public function testGenerateThrowsOnEmptyAlternatives(): void
    {
        $grammar = new Grammar('empty', [
            'empty' => new ProductionRule('empty', []),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Production rule has no alternatives.');

        $generator->generate('empty');
    }

    public function testGenerateThrowsOnNonExistentRule(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('a')]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        // Accessing a non-existent rule:
        // 1. Warning: Undefined array key "non_existent_rule"
        // 2. TypeError: Attempt to read property "alternatives" on null
        $this->expectException(\TypeError::class);

        @$generator->generate('non_existent_rule');
    }

    // =========================================================================
    // generate() - Terminal rendering (operators)
    // =========================================================================

    #[DataProvider('providerGenerateOperator')]
    public function testGenerateOperator(string $terminalName, string $expected): void
    {
        $grammar = $this->createTerminalGrammar($terminalName);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame($expected, $result);
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

    // =========================================================================
    // generate() - Terminal rendering (lexical tokens)
    // =========================================================================

    #[DataProvider('providerGenerateLexicalToken')]
    public function testGenerateLexicalToken(string $terminalName, string $pattern): void
    {
        $grammar = $this->createTerminalGrammar($terminalName);
        $faker = $this->createFakerWithProvider();
        $generator = new SqlGenerator($grammar, $faker);

        $result = $generator->generate('stmt');

        self::assertMatchesRegularExpression($pattern, $result);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateLexicalToken(): iterable
    {
        yield 'IDENT' => ['IDENT', '/^[a-z_]+\d+$/'];
        yield 'IDENT_QUOTED' => ['IDENT_QUOTED', '/^`[a-z_]+\d+`$/'];
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

    // =========================================================================
    // generate() - Terminal rendering (special cases)
    // =========================================================================

    public function testGenerateSkipsEndOfInput(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT_SYM'),
                    new Terminal('END_OF_INPUT'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('SELECT', $result);
    }

    public function testGenerateRendersWithRollupSymAsMultipleWords(): void
    {
        $grammar = $this->createTerminalGrammar('WITH_ROLLUP_SYM');
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('WITH ROLLUP', $result);
    }

    public function testGenerateStripsSymSuffix(): void
    {
        $grammar = $this->createTerminalGrammar('SELECT_SYM');
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('SELECT', $result);
    }

    public function testGenerateKeepsTerminalWithoutSymSuffix(): void
    {
        $grammar = $this->createTerminalGrammar(',');
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame(',', $result);
    }

    public function testGenerateReturnsEmptyStringForOnlyEndOfInput(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('END_OF_INPUT')]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('', $result);
    }

    // =========================================================================
    // generate() - Output spacing
    // =========================================================================

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
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('A B C', $result);
    }

    public function testGenerateSingleTokenOutputWithoutSpacing(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('SINGLE')]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        // Single token: no spacing logic applied, output as-is
        self::assertSame('SINGLE', $result);
    }

    public function testGenerateTrimsOutput(): void
    {
        // END_OF_INPUT at start would leave leading content empty,
        // and trim() removes any edge whitespace
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('A'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('A', $result);
        self::assertSame($result, trim($result));
    }

    public function testGenerateNoSpaceBetweenConsecutiveAtSymbols(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('@'),
                    new Terminal('@'),
                    new Terminal('var'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('@@var', $result);
    }

    public function testGenerateNoSpaceBetweenWordAndOpenParen(): void
    {
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
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('COUNT( * )', $result);
    }

    /**
     * Word pattern for function spacing: /^[A-Za-z_][A-Za-z0-9_]*$/
     * - Must start with letter or underscore
     * - Followed by letters, digits, or underscores
     */
    #[DataProvider('providerGenerateWordPatternForSpacing')]
    public function testGenerateWordPatternForSpacing(string $word, bool $isWord): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal($word),
                    new Terminal('('),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        if ($isWord) {
            // No space between word and (
            self::assertSame($word . '( )', $result);
        } else {
            // Space between non-word and (
            self::assertSame($word . ' ( )', $result);
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerGenerateWordPatternForSpacing(): iterable
    {
        // Valid words (no space before paren)
        yield 'single letter' => ['a', true];
        yield 'single underscore' => ['_', true];
        yield 'underscore with digits' => ['_123', true];
        yield 'letter with digit' => ['A1', true];
        yield 'mixed case with underscore' => ['myFunc_1', true];
        yield 'all uppercase' => ['COUNT', true];
        yield 'starts with underscore then letters' => ['_test', true];

        // Invalid words (space before paren)
        yield 'starts with digit' => ['123abc', false];
        yield 'only digits' => ['123', false];
        yield 'contains hyphen' => ['my-func', false];
        yield 'contains space' => ['my func', false];
        yield 'operator plus' => ['+', false];
        yield 'operator at' => ['@', true]; // @ never has space after it (user variables)
    }

    public function testGenerateNoSpaceBetweenQuotedIdentifierAndOpenParen(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('`func`'),
                    new Terminal('('),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('`func`( )', $result);
    }

    public function testGenerateSpaceBeforeOpenParenWhenPrecededByPartialBacktick(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('`incomplete'),
                    new Terminal('('),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('`incomplete ( )', $result);
    }

    public function testGenerateSpaceBeforeOpenParenWhenPrecededByOperator(): void
    {
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
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('+ ( 1 )', $result);
    }

    public function testGenerateSpaceBeforeOpenParenWhenPrecededByNumber(): void
    {
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
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('123 ( a )', $result);
    }

    public function testGenerateNoSpaceAfterAtSymbol(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('@'),
                    new Terminal('var'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        // MySQL user variables require no space: @var, not @ var
        self::assertSame('@var', $result);
    }

    // =========================================================================
    // generate() - Sanitization
    // =========================================================================

    public function testGenerateSanitizesEqualSymBeforeAll(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('EQUAL_SYM'),
                    new Terminal('ALL_SYM'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        // <=> ALL is invalid in MySQL, so it becomes = ALL
        self::assertSame('= ALL', $result);
    }

    public function testGenerateSanitizesEqualSymBeforeAny(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('EQUAL_SYM'),
                    new Terminal('ANY_SYM'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        // <=> ANY is invalid in MySQL, so it becomes = ANY
        self::assertSame('= ANY', $result);
    }

    public function testGenerateKeepsEqualSymBeforeOtherTokens(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('EQUAL_SYM'),
                    new Terminal('NULL_SYM'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('<=> NULL', $result);
    }

    public function testGenerateKeepsEqualSymAtEnd(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                    new Terminal('EQUAL_SYM'),
                ]),
            ]),
        ]);
        $generator = new SqlGenerator($grammar, $this->faker);

        $result = $generator->generate('stmt');

        self::assertSame('a <=>', $result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createTerminalGrammar(string $terminalName): Grammar
    {
        return new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
    }

    private function createFakerWithProvider(): FakerGenerator
    {
        $faker = Factory::create();
        $faker->seed(12345);
        new MySqlProvider($faker);

        return $faker;
    }
}
