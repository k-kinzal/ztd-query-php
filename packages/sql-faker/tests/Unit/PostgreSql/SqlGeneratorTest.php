<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Faker\Factory;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\PostgreSql\SqlGenerator;
use SqlFaker\PostgreSqlProvider;

#[CoversClass(SqlGenerator::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(PostgreSqlProvider::class)]
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

    #[DataProvider('providerCanonicalIdentifierRule')]
    public function testAugmentGrammarKeepsCanonicalIdentifierRules(string $ruleName): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        $rule = $augmented->ruleMap[$ruleName];
        self::assertCount(1, $rule->alternatives);
        $symbol = $rule->alternatives[0]->symbols[0] ?? null;
        self::assertInstanceOf(Terminal::class, $symbol);
        self::assertSame('IDENT', $symbol->value);
    }

    public function testAugmentGrammarRemovesBracketIndirection(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['indirection_el']->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;

                return $first instanceof Terminal && $first->value === '[';
            },
        )));
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

    public function testGenerateKeepsExplicitLongQualifiedNameUnchanged(): void
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

        self::assertSame('a.b.c.d.e', $result);
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

    public function testAugmentGrammarRemovesBareOperatorDefinitionElement(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['operator_def_elem']->alternatives,
            static function (Production $alt): bool {
                return count($alt->symbols) === 1
                    && $alt->symbols[0] instanceof NonTerminal
                    && $alt->symbols[0]->value === 'ColLabel';
            },
        )));
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

    public function testAugmentGrammarRemovesDotStarIndirection(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['indirection_el']->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;
                $second = $alt->symbols[1] ?? null;

                return $first instanceof Terminal
                    && $first->value === '.'
                    && $second instanceof Terminal
                    && $second->value === '*';
            },
        )));
    }

    public function testAugmentGrammarRemovesSingleTypenameOperatorArgs(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['oper_argtypes']->alternatives,
            static function (Production $alt): bool {
                return count($alt->symbols) === 3
                    && $alt->symbols[0] instanceof Terminal
                    && $alt->symbols[0]->value === '('
                    && $alt->symbols[1] instanceof NonTerminal
                    && $alt->symbols[1]->value === 'Typename'
                    && $alt->symbols[2] instanceof Terminal
                    && $alt->symbols[2]->value === ')';
            },
        )));
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

    public function testAugmentGrammarFactorsCreateOperatorIntoRequiredProcedureDefinition(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('DefineOperatorStmt', $augmented->ruleMap);
        self::assertArrayHasKey('safe_operator_definition', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['DefineStmt']->alternatives,
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

                return $names === ['DefineOperatorStmt'];
            },
        )));
        self::assertSame(['CREATE', 'OPERATOR', 'any_operator', 'safe_operator_definition'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['DefineOperatorStmt']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarFactorsCreateAggregateIntoRequiredStateDefinition(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('DefineAggregateStmt', $augmented->ruleMap);
        self::assertArrayHasKey('safe_aggregate_definition', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['DefineStmt']->alternatives,
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

                return $names === ['DefineAggregateStmt'];
            },
        )));
        self::assertSame(['CREATE', 'opt_or_replace', 'AGGREGATE', 'func_name', 'aggr_args', 'safe_aggregate_definition'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['DefineAggregateStmt']->alternatives[0]->symbols,
        ));
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

    #[DataProvider('providerQualifiedNameRule')]
    public function testAugmentGrammarLimitsQualifiedNameDepth(string $ruleName): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        $alternatives = $augmented->ruleMap[$ruleName]->alternatives;
        self::assertCount(2, $alternatives);

        self::assertSame(['ColId'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $alternatives[0]->symbols,
        ));
        self::assertSame(['ColId', '.', 'attr_name'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $alternatives[1]->symbols,
        ));
    }

    public function testAugmentGrammarRemovesBarePublicationObjectSpecAlternatives(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['PublicationObjSpec']->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;

                return $first instanceof NonTerminal && $first->value === 'ColId';
            },
        )));
    }

    public function testAugmentGrammarUsesMaterializedViewSpecificAlterCommands(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertCount(1, $augmented->ruleMap['materialized_view_alter_table_cmds']->alternatives);
        self::assertSame(['materialized_view_alter_table_cmd'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['materialized_view_alter_table_cmds']->alternatives[0]->symbols,
        ));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['materialized_view_alter_table_cmd']->alternatives,
            static function (Production $alt): bool {
                return in_array('EXPRESSION', array_map(
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
            $augmented->ruleMap['materialized_view_alter_table_cmd']->alternatives,
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

                return $names === ['ALTER', 'opt_column', 'safe_materialized_view_column_position', 'SET', 'STATISTICS', 'safe_materialized_view_statistics_value'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['materialized_view_alter_table_cmd']->alternatives,
            static function (Production $alt): bool {
                return in_array('Iconst', array_map(
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
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['AlterTableStmt']->alternatives,
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

                return ($names === ['ALTER', 'MATERIALIZED', 'VIEW', 'qualified_name', 'alter_table_cmds'])
                    || ($names === ['ALTER', 'MATERIALIZED', 'VIEW', 'IF_P', 'EXISTS', 'qualified_name', 'alter_table_cmds']);
            },
        )));
    }

    public function testAugmentGrammarUsesViewSpecificAlterCommands(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertCount(1, $augmented->ruleMap['view_alter_table_cmds']->alternatives);
        self::assertSame(['view_alter_table_cmd'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['view_alter_table_cmds']->alternatives[0]->symbols,
        ));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['view_alter_table_cmd']->alternatives,
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

                return $names === ['ALTER', 'opt_column', 'ColId', 'SET', 'DEFAULT', 'a_expr'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['view_alter_table_cmd']->alternatives,
            static function (Production $alt): bool {
                return in_array('EXPRESSION', array_map(
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
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['AlterTableStmt']->alternatives,
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

                return ($names === ['ALTER', 'VIEW', 'qualified_name', 'alter_table_cmds'])
                    || ($names === ['ALTER', 'VIEW', 'IF_P', 'EXISTS', 'qualified_name', 'alter_table_cmds']);
            },
        )));
    }

    public function testAugmentGrammarConstrainsAlterDomainAddToCheckFamilies(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['AlterDomainStmt']->alternatives,
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

                return $names === ['ALTER', 'DOMAIN_P', 'any_name', 'ADD_P', 'DomainConstraint'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['AlterDomainStmt']->alternatives,
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

                return $names === ['ALTER', 'DOMAIN_P', 'any_name', 'ADD_P', 'domain_add_constraint'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsAccessMethodNamesToIdentifiers(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertCount(1, $augmented->ruleMap['set_access_method_name']->alternatives);
        self::assertSame(['ColId'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['set_access_method_name']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarRemovesAlterEnumDropValueAlternative(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['AlterEnumStmt']->alternatives,
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

                return $names === ['ALTER', 'TYPE_P', 'any_name', 'DROP', 'VALUE_P', 'Sconst'];
            },
        )));
    }

    public function testAugmentGrammarIntroducesFiniteArityCreateAsFamilies(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('create_as_target_no_columns_non_temp', $augmented->ruleMap);
        self::assertArrayHasKey('create_as_target_no_columns_temp', $augmented->ruleMap);
        self::assertArrayHasKey('create_as_target_non_temp_1', $augmented->ruleMap);
        self::assertArrayHasKey('create_as_target_temp_1', $augmented->ruleMap);
        self::assertArrayHasKey('ctas_select_stmt_1', $augmented->ruleMap);
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['CreateAsStmt']->alternatives,
            static function (Production $alt): bool {
                return in_array('create_as_target', array_map(
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
            $augmented->ruleMap['CreateAsStmt']->alternatives,
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

                return in_array('create_as_target_non_temp_1', $names, true)
                    || in_array('create_as_target_temp_1', $names, true);
            },
        )));
        self::assertSame(['SELECT', 'ctas_target_list_1'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['ctas_select_stmt_1']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarRemovesDefaultFromSelectValuesExpressions(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['safe_select_a_expr']->alternatives,
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

                return $names === ['DEFAULT'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['simple_select']->alternatives,
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

                return $names === ['select_values_clause'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['simple_select']->alternatives,
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

                return $names === ['SELECT', 'opt_all_clause', 'target_list', 'into_clause', 'from_clause', 'where_clause', 'group_clause', 'having_clause', 'window_clause']
                    || $names === ['set_operation_select_stmt'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['simple_select']->alternatives,
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

                return $names === ['SELECT', 'opt_all_clause', 'opt_target_list', 'into_clause', 'from_clause', 'where_clause', 'group_clause', 'having_clause', 'window_clause']
                    || $names === ['select_clause', 'UNION', 'set_quantifier', 'select_clause']
                    || $names === ['select_clause', 'INTERSECT', 'set_quantifier', 'select_clause']
                    || $names === ['select_clause', 'EXCEPT', 'set_quantifier', 'select_clause'];
            },
        )));
        self::assertArrayHasKey('setop_select_stmt_1', $augmented->ruleMap);
        self::assertArrayHasKey('setop_select_stmt_8', $augmented->ruleMap);
        self::assertArrayHasKey('select_values_clause_1', $augmented->ruleMap);
        self::assertArrayHasKey('select_value_expr_list_1', $augmented->ruleMap);
        self::assertSame(['select_values_clause_1'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['select_values_clause']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarConstrainsDistinctOnExpressions(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_distinct_on_expr', $augmented->ruleMap);
        self::assertArrayHasKey('safe_distinct_on_expr_list', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['distinct_clause']->alternatives,
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

                return $names === ['DISTINCT', 'ON', '(', 'safe_distinct_on_expr_list', ')'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['distinct_clause']->alternatives,
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

                return $names === ['DISTINCT', 'ON', '(', 'expr_list', ')'];
            },
        )));
    }

    public function testAugmentGrammarFactorsTextSearchTemplateIntoDedicatedDefinitionFamily(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('DefineTextSearchTemplateStmt', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['DefineStmt']->alternatives,
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

                return $names === ['DefineTextSearchTemplateStmt'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['text_search_template_definition']->alternatives,
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

                return $names === ['(', 'text_search_template_lexize_option', ')']
                    || $names === ['(', 'text_search_template_init_option', ',', 'text_search_template_lexize_option', ')']
                    || $names === ['(', 'text_search_template_lexize_option', ',', 'text_search_template_init_option', ')'];
            },
        )));
    }

    public function testAugmentGrammarRequiresCompleteCreateRoutineDefinitions(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_create_routine_sql_options', $augmented->ruleMap);
        self::assertArrayHasKey('safe_create_routine_return_body', $augmented->ruleMap);
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['CreateFunctionStmt']->alternatives,
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

                return $names === ['CREATE', 'opt_or_replace', 'FUNCTION', 'func_name', 'func_args_with_defaults', 'RETURNS', 'func_return', 'opt_createfunc_opt_list', 'opt_routine_body']
                    || $names === ['CREATE', 'opt_or_replace', 'FUNCTION', 'func_name', 'func_args_with_defaults', 'RETURNS', 'TABLE', '(', 'table_func_column_list', ')', 'opt_createfunc_opt_list', 'opt_routine_body'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['CreateFunctionStmt']->alternatives,
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

                return $names === ['CREATE', 'opt_or_replace', 'FUNCTION', 'func_name', 'safe_create_routine_args', 'RETURNS', 'safe_create_routine_return_type', 'safe_create_routine_sql_options']
                    || $names === ['CREATE', 'opt_or_replace', 'FUNCTION', 'func_name', 'safe_create_routine_args', 'RETURNS', 'safe_create_routine_return_type', 'safe_create_routine_return_body']
                    || $names === ['CREATE', 'opt_or_replace', 'PROCEDURE', 'func_name', 'safe_create_routine_args', 'safe_create_routine_sql_options'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsAlterRoutineOptionsToSafeSingletons(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_alter_routine_option', $augmented->ruleMap);
        self::assertArrayHasKey('safe_alter_routine_option_list', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['AlterFunctionStmt']->alternatives,
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

                return $names === ['ALTER', 'ROUTINE', 'function_with_argtypes', 'safe_alter_routine_option_list', 'opt_restrict'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['AlterFunctionStmt']->alternatives,
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

                return $names === ['ALTER', 'ROUTINE', 'function_with_argtypes', 'alterfunc_opt_list', 'opt_restrict'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsGrantParameterTargetsToCanonicalConfigurationNames(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_configuration_parameter_name', $augmented->ruleMap);
        self::assertArrayHasKey('safe_configuration_parameter_name_list', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['privilege_target']->alternatives,
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

                return $names === ['PARAMETER', 'safe_configuration_parameter_name_list'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['privilege_target']->alternatives,
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

                return $names === ['PARAMETER', 'parameter_name_list'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['safe_configuration_parameter_name']->alternatives,
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

                return $names === ['search_path'] || $names === ['work_mem'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsCreateRoleNamesAndOptions(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_create_role_name', $augmented->ruleMap);
        self::assertArrayHasKey('safe_create_role_option_list', $augmented->ruleMap);
        self::assertSame(['CREATE', 'ROLE', 'safe_create_role_name', 'opt_with', 'safe_create_role_option_list'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['CreateRoleStmt']->alternatives[0]->symbols,
        ));
        self::assertSame(['CREATE', 'USER', 'safe_create_role_name', 'opt_with', 'safe_create_role_option_list'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['CreateUserStmt']->alternatives[0]->symbols,
        ));
        self::assertSame(['CREATE', 'GROUP_P', 'safe_create_role_name', 'opt_with', 'safe_create_role_option_list'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['CreateGroupStmt']->alternatives[0]->symbols,
        ));
        self::assertSame(['NonReservedWord'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['safe_create_role_name']->alternatives[0]->symbols,
        ));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['safe_create_role_option']->alternatives,
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

                return in_array('SESSION_USER', $names, true)
                    || in_array('CURRENT_USER', $names, true)
                    || in_array('CURRENT_ROLE', $names, true);
            },
        )));
        self::assertArrayHasKey('safe_role_name_list', $augmented->ruleMap);
        self::assertSame(['DROP', 'GROUP_P', 'IF_P', 'EXISTS', 'safe_role_name_list'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['DropRoleStmt']->alternatives[5]->symbols,
        ));
        self::assertSame(['GRANT', 'safe_role_name_list', 'TO', 'safe_role_name_list', 'opt_granted_by'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['GrantRoleStmt']->alternatives[0]->symbols,
        ));
        self::assertSame(['REVOKE', 'safe_role_name_list', 'FROM', 'safe_role_name_list', 'opt_granted_by', 'opt_drop_behavior'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['RevokeRoleStmt']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarIntroducesAlterIndexStatementFamily(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('AlterIndexStmt', $augmented->ruleMap);
        self::assertArrayHasKey('index_alter_table_cmd', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['AlterTableStmt']->alternatives,
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

                return $names === ['AlterIndexStmt'];
            },
        )));
        self::assertSame(['ALTER', 'INDEX', 'qualified_name', 'index_alter_table_cmds'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['AlterIndexStmt']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarFactorsCreatePartitionOfIntoNonTemporaryFamily(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('CreatePartitionOfStmt', $augmented->ruleMap);
        self::assertArrayHasKey('safe_partition_of_opt_temp', $augmented->ruleMap);
        self::assertSame([[]], array_map(
            static function (Production $alt): array {
                return array_map(
                    static function ($symbol): string {
                        return match (true) {
                            $symbol instanceof NonTerminal => $symbol->value,
                            $symbol instanceof Terminal => $symbol->value,
                            default => throw new LogicException('Unexpected symbol type.'),
                        };
                    },
                    $alt->symbols,
                );
            },
            $augmented->ruleMap['safe_partition_of_opt_temp']->alternatives,
        ));
    }

    public function testAugmentGrammarBindsTemporaryRelationFamiliesToUnqualifiedNames(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_temporary_relation_name', $augmented->ruleMap);
        self::assertArrayHasKey('safe_temporary_relation_modifier', $augmented->ruleMap);
        self::assertSame(['ColId'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['safe_temporary_relation_name']->alternatives[0]->symbols,
        ));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['CreateStmt']->alternatives,
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

                return $names === ['CREATE', 'safe_temporary_relation_modifier', 'TABLE', 'safe_temporary_relation_name', '(', 'OptTableElementList', ')', 'OptInherit', 'OptPartitionSpec', 'table_access_method_clause', 'OptWith', 'OnCommitOption', 'OptTableSpace'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['CreateSeqStmt']->alternatives,
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

                return $names === ['CREATE', 'safe_temporary_relation_modifier', 'SEQUENCE', 'safe_temporary_relation_name', 'OptSeqOptList'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['ExecuteStmt']->alternatives,
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

                return $names === ['CREATE', 'safe_temporary_relation_modifier', 'TABLE', 'execute_create_as_target_temp', 'AS', 'EXECUTE', 'name', 'execute_param_clause', 'opt_with_data'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['ViewStmt']->alternatives,
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

                return $names === ['CREATE', 'safe_temporary_relation_modifier', 'VIEW', 'safe_temporary_relation_name', 'opt_reloptions', 'AS', 'SelectStmt', 'opt_check_option'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsAlterExtensionContentTargets(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_extension_object_type_name', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['AlterExtensionContentsStmt']->alternatives,
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

                return $names === ['ALTER', 'EXTENSION', 'name', 'add_drop', 'safe_extension_object_type_name', 'name']
                    || $names === ['ALTER', 'EXTENSION', 'name', 'add_drop', 'TYPE_P', 'any_name'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['safe_extension_object_type_name']->alternatives,
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

                return $names === ['EXTENSION'] || $names === ['ROLE'] || $names === ['DATABASE'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsLargeObjectTargetsToIntegerOidSubset(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_large_object_oid', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['privilege_target']->alternatives,
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

                return $names === ['LARGE_P', 'OBJECT_P', 'safe_large_object_oid_list'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['privilege_target']->alternatives,
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

                return $names === ['LARGE_P', 'OBJECT_P', 'NumericOnly_list'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsPartitionStrategiesToKeywords(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_partition_strategy', $augmented->ruleMap);
        self::assertSame(['PARTITION', 'BY', 'safe_partition_strategy', '(', 'part_params', ')'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['PartitionSpec']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarConstrainsViewColumnsToFiniteArityFamilies(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_view_non_temp_modifier', $augmented->ruleMap);
        self::assertArrayHasKey('view_column_list_1', $augmented->ruleMap);
        self::assertArrayHasKey('view_select_stmt_1', $augmented->ruleMap);
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['safe_view_non_temp_modifier']->alternatives,
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

                return $names === ['UNLOGGED'];
            },
        )));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['ViewStmt']->alternatives,
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

                return $names === ['CREATE', 'safe_view_non_temp_modifier', 'RECURSIVE', 'VIEW', 'qualified_name', 'view_column_list_1', 'opt_reloptions', 'AS', 'view_select_stmt_1', 'opt_check_option']
                    || $names === ['CREATE', 'safe_temporary_relation_modifier', 'RECURSIVE', 'VIEW', 'safe_temporary_relation_name', 'view_column_list_1', 'opt_reloptions', 'AS', 'view_select_stmt_1', 'opt_check_option'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsInsertColumnsToFiniteArityFamilies(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('insert_column_list_1', $augmented->ruleMap);
        self::assertArrayHasKey('insert_select_stmt_1', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['insert_rest']->alternatives,
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

                return $names === ['(', 'insert_column_list_1', ')', 'insert_select_stmt_1'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['insert_rest']->alternatives,
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

                return $names === ['(', 'insert_column_list', ')', 'SelectStmt'];
            },
        )));
    }

    public function testAugmentGrammarRequiresConflictInferenceForInsertUpdate(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('insert_conflict_update_stmt', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['opt_on_conflict']->alternatives,
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

                return $names === ['ON', 'CONFLICT', 'safe_conf_expr', 'DO', 'UPDATE', 'SET', 'safe_insert_conflict_set_clause_list', 'where_clause'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsAlterSequenceTableCommands(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('sequence_alter_table_cmds', $augmented->ruleMap);
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['AlterTableStmt']->alternatives,
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

                return $names === ['ALTER', 'SEQUENCE', 'qualified_name', 'alter_table_cmds'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsAlterStatisticsValues(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_alter_statistics_value', $augmented->ruleMap);
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['safe_alter_statistics_value']->alternatives,
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

                return $names === ['DEFAULT'];
            },
        )));
    }

    public function testAugmentGrammarUsesObjectNamesForDropTypeFamilies(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_drop_type_name_list', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['DropStmt']->alternatives,
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

                return $names === ['DROP', 'TYPE_P', 'safe_drop_type_name_list', 'opt_drop_behavior']
                    || $names === ['DROP', 'DOMAIN_P', 'safe_drop_type_name_list', 'opt_drop_behavior'];
            },
        )));
        self::assertSame([], array_values(array_filter(
            $augmented->ruleMap['DropStmt']->alternatives,
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

                return $names === ['DROP', 'TYPE_P', 'type_name_list', 'opt_drop_behavior']
                    || $names === ['DROP', 'DOMAIN_P', 'type_name_list', 'opt_drop_behavior'];
            },
        )));
    }

    public function testAugmentGrammarConstrainsCommentTypeReferencesAndCreateCastTypes(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('CommentTypeReferenceStmt', $augmented->ruleMap);
        self::assertArrayHasKey('safe_type_reference', $augmented->ruleMap);
        self::assertArrayHasKey('safe_cast_signature', $augmented->ruleMap);
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['CommentStmt']->alternatives,
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

                return $names === ['CommentTypeReferenceStmt'];
            },
        )));
        self::assertArrayHasKey('safe_cast_function_with_argtypes', $augmented->ruleMap);
        self::assertSame(['CREATE', 'CAST', 'safe_cast_signature', 'WITH', 'FUNCTION', 'safe_cast_function_with_argtypes', 'cast_context'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['CreateCastStmt']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarConstrainsDropCastAndCreateAssertionFamilies(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertSame(['DROP', 'CAST', 'opt_if_exists', 'safe_cast_signature', 'opt_drop_behavior'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['DropCastStmt']->alternatives[0]->symbols,
        ));
        self::assertArrayHasKey('safe_assertion_check_expr', $augmented->ruleMap);
        self::assertSame(['CREATE', 'ASSERTION', 'any_name', 'CHECK', '(', 'safe_assertion_check_expr', ')', 'ConstraintAttributeSpec'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['CreateAssertionStmt']->alternatives[0]->symbols,
        ));
    }

    public function testAugmentGrammarConstrainsAlterTypeToSafeOptionSubset(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertArrayHasKey('safe_alter_type_option', $augmented->ruleMap);
        self::assertArrayHasKey('safe_alter_type_option_list', $augmented->ruleMap);
        self::assertSame(['ALTER', 'TYPE_P', 'any_name', 'SET', '(', 'safe_alter_type_option_list', ')'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['AlterTypeStmt']->alternatives[0]->symbols,
        ));
        self::assertNotSame([], array_values(array_filter(
            $augmented->ruleMap['safe_alter_type_option']->alternatives,
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

                return $names === ['RECEIVE', '=', 'NONE'];
            },
        )));
    }

    public function testAugmentGrammarRewritesDoStmtToCanonicalBody(): void
    {
        $grammar = \SqlFaker\PostgreSql\Grammar\PgGrammar::load();
        $faker = Factory::create();
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $ref = new ReflectionClass($generator);
        $prop = $ref->getProperty('grammar');
        /** @var Grammar $augmented */
        $augmented = $prop->getValue($generator);

        self::assertCount(1, $augmented->ruleMap['DoStmt']->alternatives);
        self::assertSame(['DO', 'DO_BODY_SCONST'], array_map(
            static function ($symbol): string {
                return match (true) {
                    $symbol instanceof NonTerminal => $symbol->value,
                    $symbol instanceof Terminal => $symbol->value,
                    default => throw new LogicException('Unexpected symbol type.'),
                };
            },
            $augmented->ruleMap['DoStmt']->alternatives[0]->symbols,
        ));
    }

    public function testGenerateDoBodyLiteral(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Terminal('DO_BODY_SCONST'),
                ]),
            ]),
        ]);
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = new SqlGenerator($grammar, $faker, new PostgreSqlProvider($faker));

        $result = $generator->generate('stmt');

        self::assertSame("'BEGIN NULL; END'", $result);
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
     * @return iterable<string, array{string}>
     */
    public static function providerCanonicalIdentifierRule(): iterable
    {
        yield 'ColId' => ['ColId'];
        yield 'ColLabel' => ['ColLabel'];
        yield 'type_function_name' => ['type_function_name'];
        yield 'NonReservedWord' => ['NonReservedWord'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerQualifiedNameRule(): iterable
    {
        yield 'qualified_name' => ['qualified_name'];
        yield 'any_name' => ['any_name'];
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
