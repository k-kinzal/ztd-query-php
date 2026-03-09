<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;

#[CoversNothing]
final class GrammarTest extends TestCase
{
    public function testLoad(): void
    {
        $grammar = Grammar::load();

        self::assertSame('start_entry', $grammar->startSymbol);
        self::assertGreaterThan(100, count($grammar->ruleMap), 'MySQL grammar should have many rules');
    }

    public function testLoadWithDefaultVersion(): void
    {
        $grammar = Grammar::load(null);

        self::assertSame('start_entry', $grammar->startSymbol);
    }

    public function testLoadWithExplicitVersion(): void
    {
        $grammar = Grammar::load('mysql-8.4.7');

        self::assertSame('start_entry', $grammar->startSymbol);
        self::assertGreaterThan(100, count($grammar->ruleMap));
    }

    public function testLoadWithNonExistentVersionThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Grammar file not found');

        Grammar::load('non-existent-version');
    }

    public function testLoadedGrammarHasExpectedStructure(): void
    {
        $grammar = Grammar::load();

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
        $grammar56 = Grammar::load('mysql-5.6.51');
        $grammar84 = Grammar::load('mysql-8.4.7');

        self::assertNotSame(count($grammar56->ruleMap), count($grammar84->ruleMap));
    }
}
