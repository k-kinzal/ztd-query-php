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

#[CoversClass(MySqlGrammar::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(NonTerminal::class)]
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
}
