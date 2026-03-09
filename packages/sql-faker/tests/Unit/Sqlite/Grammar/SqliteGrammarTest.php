<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Grammar;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;

#[CoversNothing]
final class SqliteGrammarTest extends TestCase
{
    public function testLoad(): void
    {
        $grammar = SqliteGrammar::load();

        self::assertInstanceOf(Grammar::class, $grammar);
    }

    public function testLoadWithExplicitVersion(): void
    {
        $grammar = SqliteGrammar::load('sqlite-3.47.2');

        self::assertInstanceOf(Grammar::class, $grammar);
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
