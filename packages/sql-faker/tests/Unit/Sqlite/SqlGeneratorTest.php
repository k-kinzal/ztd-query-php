<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\Sqlite\SqlGenerator;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\SqliteProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SqlGenerator::class)]
#[CoversClass(TokenJoiner::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(SqliteProvider::class)]
#[CoversClass(SqliteGrammar::class)]
#[CoversClass(TerminationAnalyzer::class)]
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
     * @return iterable<string, array{string, string}>
     */
    public static function providerGenerateCompoundKeyword(): iterable
    {
        yield 'JOIN_KW' => ['JOIN_KW', '/^(LEFT|RIGHT|INNER|CROSS|NATURAL LEFT|NATURAL INNER|NATURAL CROSS)$/'];
        yield 'CTIME_KW' => ['CTIME_KW', '/^(CURRENT_TIME|CURRENT_DATE|CURRENT_TIMESTAMP)$/'];
        yield 'LIKE_KW' => ['LIKE_KW', '/^(LIKE|GLOB)$/'];
    }
}
