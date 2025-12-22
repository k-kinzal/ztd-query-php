<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Faker\Factory;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\PostgreSql\SqlGenerator;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\PostgreSqlProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SqlGenerator::class)]
#[CoversClass(TokenJoiner::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(PostgreSqlProvider::class)]
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
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SELECT foo', $result);
    }

    public function testGenerateDefaultStartRule(): void
    {
        $grammar = new Grammar('stmtmulti', [
            'stmtmulti' => new ProductionRule('stmtmulti', [
                new Production([new Terminal('DEFAULT_RULE_USED')]),
            ]),
            'other_rule' => new ProductionRule('other_rule', [
                new Production([new Terminal('OTHER_RULE_USED')]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

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
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result1 = $generator->generate('stmt');
        $result2 = $generator->generate('stmt');

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
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt', 1);

        self::assertSame('SHORT', $result);
    }

    public function testGenerateThrowsOnDerivationLimit(): void
    {
        $reflection = new ReflectionClass(SqlGenerator::class);
        $constant = $reflection->getConstant('DERIVATION_LIMIT');
        self::assertSame(5000, $constant);

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
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Exceeded derivation limit while generating SQL.');

        $generator->generate('infinite');
    }

    public function testGenerateThrowsOnEmptyAlternatives(): void
    {
        $grammar = new Grammar('empty', [
            'empty' => new ProductionRule('empty', []),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Production rule has no alternatives.');

        $generator->generate('empty');
    }

    #[DataProvider('providerGenerateOperator')]
    public function testGenerateOperator(string $terminalName, string $expected): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

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
        $provider = new PostgreSqlProvider($faker);
        $generator = new SqlGenerator($grammar, $faker, $provider);

        $result = $generator->generate('stmt');

        self::assertMatchesRegularExpression($pattern, $result);
    }

    #[DataProvider('providerGenerateLookaheadToken')]
    public function testGenerateLookaheadToken(string $terminalName, string $expected): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame($expected, $result);
    }

    #[DataProvider('providerGenerateStripsPSuffix')]
    public function testGenerateStripsPSuffix(string $terminalName, string $expected): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal($terminalName)]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame($expected, $result);
    }

    public function testGenerateTruncatesQualifiedNameToThreeParts(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                    new Terminal('.'),
                    new Terminal('b'),
                    new Terminal('.'),
                    new Terminal('c'),
                    new Terminal('.'),
                    new Terminal('d'),
                    new Terminal('.'),
                    new Terminal('e'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('a.b.c', $result);
    }

    public function testGenerateKeepsThreePartQualifiedName(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                    new Terminal('.'),
                    new Terminal('b'),
                    new Terminal('.'),
                    new Terminal('c'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('a.b.c', $result);
    }

    public function testGenerateKeepsSingleIdentifier(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('a', $result);
    }

    public function testGenerateSanitizesBareColLabelInSetParens(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SET'),
                    new Terminal('('),
                    new Terminal('foo'),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SET(foo = NONE)', $result);
    }

    public function testGenerateKeepsSetWithEqualsUnchanged(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SET'),
                    new Terminal('('),
                    new Terminal('foo'),
                    new Terminal('='),
                    new Terminal('bar'),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SET(foo = bar)', $result);
    }

    public function testGenerateStripsDotStarFromQualifiedName(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                    new Terminal('.'),
                    new Terminal('*'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('a', $result);
    }

    public function testGenerateStripsDotStarAfterQualifiedNameChain(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                    new Terminal('.'),
                    new Terminal('b'),
                    new Terminal('.'),
                    new Terminal('*'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('a.b', $result);
    }

    public function testGenerateInsertsNoneForSingleOperatorArgType(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('DROP'),
                    new Terminal('OPERATOR'),
                    new Terminal('myop'),
                    new Terminal('('),
                    new Terminal('int4'),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('DROP OPERATOR myop(NONE, int4)', $result);
    }

    public function testGenerateKeepsTwoTypeOperatorArgs(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('DROP'),
                    new Terminal('OPERATOR'),
                    new Terminal('myop'),
                    new Terminal('('),
                    new Terminal('int4'),
                    new Terminal(','),
                    new Terminal('int4'),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('DROP OPERATOR myop(int4, int4)', $result);
    }

    public function testGenerateSkipsExpressionContextOperator(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('OPERATOR'),
                    new Terminal('('),
                    new Terminal('pg_catalog'),
                    new Terminal('.'),
                    new Terminal('+'),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertStringNotContainsString('NONE', $result);
    }

    public function testGenerateSetWithUnclosedParenKeepsTokensUnchanged(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SET'),
                    new Terminal('('),
                    new Terminal('foo'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SET(foo', $result);
    }

    public function testGenerateSetWithNestedParensSkipsInnerIdentifiers(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SET'),
                    new Terminal('('),
                    new Terminal('foo'),
                    new Terminal(','),
                    new Terminal('bar'),
                    new Terminal('('),
                    new Terminal('x'),
                    new Terminal(')'),
                    new Terminal(','),
                    new Terminal('baz'),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('SET(foo = NONE, bar(x), baz = NONE)', $result);
    }

    public function testGenerateOperatorArgTypesWithUnclosedParenKeepsTokensUnchanged(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('DROP'),
                    new Terminal('OPERATOR'),
                    new Terminal('myop'),
                    new Terminal('('),
                    new Terminal('int4'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('DROP OPERATOR myop(int4', $result);
    }

    public function testGenerateOperatorArgTypesWithNoParenKeepsTokensUnchanged(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('DROP'),
                    new Terminal('OPERATOR'),
                    new Terminal('myop'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame('DROP OPERATOR myop', $result);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateOperator(): iterable
    {
        yield 'TYPECAST' => ['TYPECAST', '::'];
        yield 'DOT_DOT' => ['DOT_DOT', '..'];
        yield 'COLON_EQUALS' => ['COLON_EQUALS', ':='];
        yield 'EQUALS_GREATER' => ['EQUALS_GREATER', '=>'];
        yield 'NOT_EQUALS' => ['NOT_EQUALS', '!='];
        yield 'LESS_EQUALS' => ['LESS_EQUALS', '<='];
        yield 'GREATER_EQUALS' => ['GREATER_EQUALS', '>='];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateLexicalToken(): iterable
    {
        yield 'IDENT' => ['IDENT', '/^[a-z_][a-z0-9_]*$/'];
        yield 'SCONST' => ['SCONST', "/^'[a-zA-Z0-9_]+'$/"];
        yield 'ICONST' => ['ICONST', '/^[1-9]\d*$/'];
        yield 'FCONST' => ['FCONST', '/^\d+\.\d+$/'];
        yield 'BCONST' => ['BCONST', "/^B'[01]+'$/"];
        yield 'XCONST' => ['XCONST', "/^X'[0-9a-f]+'$/"];
        yield 'Op' => ['Op', '/^[+\-*\/<>=~!@#%^&|]$/'];
        yield 'PARAM' => ['PARAM', '/^\$\d+$/'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateLookaheadToken(): iterable
    {
        yield 'NOT_LA' => ['NOT_LA', 'NOT'];
        yield 'NULLS_LA' => ['NULLS_LA', 'NULLS'];
        yield 'WITH_LA' => ['WITH_LA', 'WITH'];
        yield 'WITHOUT_LA' => ['WITHOUT_LA', 'WITHOUT'];
        yield 'FORMAT_LA' => ['FORMAT_LA', 'FORMAT'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateStripsPSuffix(): iterable
    {
        yield 'ABORT_P' => ['ABORT_P', 'ABORT'];
        yield 'BEGIN_P' => ['BEGIN_P', 'BEGIN'];
        yield 'END_P' => ['END_P', 'END'];
        yield 'BOOLEAN_P' => ['BOOLEAN_P', 'BOOLEAN'];
        yield 'DELETE_P' => ['DELETE_P', 'DELETE'];
        yield 'NULL_P' => ['NULL_P', 'NULL'];
        yield 'TRUE_P' => ['TRUE_P', 'TRUE'];
        yield 'FALSE_P' => ['FALSE_P', 'FALSE'];
    }

    public function testGenerateSpacing(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $grammarFuncParen = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('COUNT'),
                    new Terminal('('),
                    new Terminal('*'),
                    new Terminal(')'),
                ]),
            ]),
        ]);
        $result = (new SqlGenerator($grammarFuncParen, $faker, $provider))->generate('stmt');
        self::assertSame('COUNT(*)', $result);

        $grammarDot = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('schema'),
                    new Terminal('.'),
                    new Terminal('table'),
                ]),
            ]),
        ]);
        $result = (new SqlGenerator($grammarDot, $faker, $provider))->generate('stmt');
        self::assertSame('schema.table', $result);

        $grammarTypecast = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('col'),
                    new Terminal('TYPECAST'),
                    new Terminal('INTEGER'),
                ]),
            ]),
        ]);
        $result = (new SqlGenerator($grammarTypecast, $faker, $provider))->generate('stmt');
        self::assertSame('col::INTEGER', $result);

        $grammarBracket = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('arr'),
                    new Terminal('['),
                    new Terminal('1'),
                    new Terminal(']'),
                ]),
            ]),
        ]);
        $result = (new SqlGenerator($grammarBracket, $faker, $provider))->generate('stmt');
        self::assertSame('arr [1]', $result);

        $grammarComma = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('a'),
                    new Terminal(','),
                    new Terminal('b'),
                ]),
            ]),
        ]);
        $result = (new SqlGenerator($grammarComma, $faker, $provider))->generate('stmt');
        self::assertSame('a, b', $result);

        $grammarSemicolon = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('SELECT'),
                    new Terminal('1'),
                    new Terminal(';'),
                ]),
            ]),
        ]);
        $result = (new SqlGenerator($grammarSemicolon, $faker, $provider))->generate('stmt');
        self::assertSame('SELECT 1;', $result);
    }
}
