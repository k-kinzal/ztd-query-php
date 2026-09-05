<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\SqlVersionRegistry;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Grammar\MySqlGrammar;

#[CoversClass(Grammar::class)]
#[CoversClass(MySqlGrammar::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(Production::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Terminal::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(SqlVersionRegistry::class)]
final class MySqlGrammarTest extends TestCase
{
    public function testLoad(): void
    {
        $grammar = MySqlGrammar::load();

        self::assertNotEmpty($grammar->ruleMap);
    }

    public function testLoadWithExplicitVersion(): void
    {
        $grammar = MySqlGrammar::load('mysql-8.4.7');

        self::assertNotEmpty($grammar->ruleMap);
    }

    public function testResolveVersionUsesExactConfiguredDefault(): void
    {
        self::assertSame('mysql-8.4.7', MySqlGrammar::resolveVersion());
        self::assertSame('mysql-8.4.7', MySqlGrammar::resolveVersion('mysql-8.4.7'));
    }

    public function testLoadWithNonExistentVersionThrows(): void
    {
        $this->expectException(RuntimeException::class);

        MySqlGrammar::load('mysql-999.999');
    }

    public function testLoadedGrammarStructure(): void
    {
        $grammar = MySqlGrammar::load();

        self::assertArrayHasKey('start_entry', $grammar->ruleMap);
        self::assertArrayHasKey('select_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('insert_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('update_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('delete_stmt', $grammar->ruleMap);
    }

    public function testLoadPreservesCompiledStructure(): void
    {
        $grammar = MySqlGrammar::load();

        self::assertSame('start_entry', $grammar->startSymbol);
        self::assertGreaterThan(100, count($grammar->ruleMap), 'MySQL grammar should have many rules');
    }

    public function testLoadWithDefaultVersion(): void
    {
        $grammar = MySqlGrammar::load(null);

        self::assertSame('start_entry', $grammar->startSymbol);
    }

    public function testResolveVersionUsesExactConfiguredDefaultPreservesCompiledStructure(): void
    {
        self::assertSame('mysql-8.4.7', MySqlGrammar::resolveVersion());
        self::assertSame('mysql-5.7.44', MySqlGrammar::resolveVersion('mysql-5.7.44'));
    }

    public function testLoadWithExplicitVersionPreservesCompiledStructure(): void
    {
        $grammar = MySqlGrammar::load('mysql-8.4.7');

        self::assertSame('start_entry', $grammar->startSymbol);
        self::assertGreaterThan(100, count($grammar->ruleMap));
    }

    public function testLoadWithNonExistentVersionThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported mysql version');

        MySqlGrammar::load('non-existent-version');
    }

    public function testLoadedGrammarHasExpectedStructure(): void
    {
        $grammar = MySqlGrammar::load();

        self::assertArrayHasKey('select_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('insert_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('update_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('delete_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('create_table_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('alter_table_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('drop_table_stmt', $grammar->ruleMap);

        $selectRule = $grammar->ruleMap['select_stmt'];
        self::assertSame('select_stmt', $selectRule->lhs);
        self::assertGreaterThanOrEqual(1, count($selectRule->alternatives));
    }

    public function testLoadedGrammarVersionsProduceDifferentRuleCounts(): void
    {
        $grammar56 = MySqlGrammar::load('mysql-5.6.51');
        $grammar84 = MySqlGrammar::load('mysql-8.4.7');

        self::assertNotSame(count($grammar56->ruleMap), count($grammar84->ruleMap));
    }

}
