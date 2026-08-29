<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Grammar\Source\SqlVersion;
use SqlFaker\Grammar\Source\SqlVersionRegistry;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;

#[CoversClass(SqliteGrammar::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(NonTerminal::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(SqlVersionRegistry::class)]
final class SqliteGrammarTest extends TestCase
{
    public function testLoad(): void
    {
        $grammar = SqliteGrammar::load();

        self::assertNotEmpty($grammar->ruleMap);
    }

    public function testLoadWithExplicitVersion(): void
    {
        $grammar = SqliteGrammar::load('sqlite-3.47.2');

        self::assertNotEmpty($grammar->ruleMap);
    }

    public function testResolveVersionUsesExactConfiguredDefault(): void
    {
        self::assertSame('sqlite-3.47.2', SqliteGrammar::resolveVersion());
        self::assertSame('sqlite-3.47.2', SqliteGrammar::resolveVersion('sqlite-3.47.2'));
    }

    public function testLoadWithNonExistentVersionThrows(): void
    {
        $this->expectException(RuntimeException::class);

        SqliteGrammar::load('sqlite-999.999');
    }

    public function testLoadedGrammarStartSymbol(): void
    {
        $grammar = SqliteGrammar::load();

        self::assertArrayHasKey($grammar->startSymbol, $grammar->ruleMap);
    }

    public function testLoadedGrammarStructure(): void
    {
        $grammar = SqliteGrammar::load();

        self::assertArrayHasKey('cmd', $grammar->ruleMap);
        self::assertArrayHasKey('select', $grammar->ruleMap);
        self::assertArrayHasKey('expr', $grammar->ruleMap);
    }
}
